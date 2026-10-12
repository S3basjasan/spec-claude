// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import { Route } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { RequireAdmin } from './RequireAdmin'

vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function renderComo(role: 'admin' | 'user' | null) {
  vi.mocked(useCurrentUser).mockReturnValue({
    data: role ? { id: 1, name: 'Ana', email: 'a@hrms.test', role } : undefined,
  } as ReturnType<typeof useCurrentUser>)
  renderRoutes(
    <Route element={<RequireAdmin />}>
      <Route path="/competencias/nueva" element={<p>Formulario</p>} />
    </Route>,
    '/competencias/nueva',
  )
}

describe('RequireAdmin', () => {
  it('deja pasar al Administrador', () => {
    renderComo('admin')

    expect(screen.getByText('Formulario')).toBeInTheDocument()
  })

  it('al Usuario le muestra que no tiene permisos y un enlace al listado', () => {
    renderComo('user')

    expect(screen.queryByText('Formulario')).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'No tienes permisos para esta acción' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver al listado' })).toHaveAttribute('href', '/competencias')
  })

  it('sin sesión cargada tampoco muestra el formulario', () => {
    renderComo(null)

    expect(screen.queryByText('Formulario')).not.toBeInTheDocument()
  })
})
