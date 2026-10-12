// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AppShell } from './AppShell'

function renderShell(props: Partial<Parameters<typeof AppShell>[0]> = {}) {
  const onLogout = vi.fn()
  render(
    <MemoryRouter>
      <Routes>
        <Route
          element={
            <AppShell
              user={{ name: 'Ana Pérez', role: 'admin' }}
              navItems={[{ to: '/', label: 'Inicio', icon: <span />, end: true }]}
              onLogout={onLogout}
              isLoggingOut={false}
              {...props}
            />
          }
        >
          <Route index element={<p>Contenido</p>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  )
  return { onLogout }
}

beforeEach(() => {
  localStorage.clear()
  delete document.documentElement.dataset.theme
})

describe('AppShell', () => {
  it('muestra header, navegación activa, contenido y footer', () => {
    renderShell()

    expect(screen.getByRole('banner')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Inicio' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByText('Contenido')).toBeInTheDocument()
    expect(screen.getByRole('contentinfo')).toHaveTextContent('Versión 0.1')
  })

  it('muestra nombre y rol en el menú de usuario', async () => {
    renderShell({ user: { name: 'Luis Mora', role: 'user' } })

    await userEvent.setup().click(screen.getByRole('button', { name: /Menú de usuario: Luis Mora/ }))

    expect(screen.getByRole('menu')).toHaveTextContent('Luis Mora')
    expect(screen.getByRole('menu')).toHaveTextContent('Usuario')
  })

  it('cierra sesión desde el menú', async () => {
    const { onLogout } = renderShell()
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: /Menú de usuario/ }))
    await user.click(screen.getByRole('menuitem', { name: 'Cerrar sesión' }))

    expect(onLogout).toHaveBeenCalledOnce()
  })

  it('deshabilita el cierre de sesión mientras está en curso', async () => {
    renderShell({ isLoggingOut: true })

    await userEvent.setup().click(screen.getByRole('button', { name: /Menú de usuario/ }))

    expect(screen.getByRole('menuitem', { name: 'Cerrando sesión…' })).toBeDisabled()
  })

  it('cierra el menú con Escape', async () => {
    renderShell()
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: /Menú de usuario/ }))
    await user.keyboard('{Escape}')

    expect(screen.queryByRole('menu')).not.toBeInTheDocument()
  })

  it('alterna el tema y lo persiste', async () => {
    renderShell()

    await userEvent.setup().click(screen.getByRole('button', { name: 'Cambiar a tema oscuro' }))

    expect(document.documentElement.dataset.theme).toBe('dark')
    expect(localStorage.getItem('hrms.theme')).toBe('dark')
    expect(screen.getByRole('button', { name: 'Cambiar a tema claro' })).toBeInTheDocument()
  })
})
