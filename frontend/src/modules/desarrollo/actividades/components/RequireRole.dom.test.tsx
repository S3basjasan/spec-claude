// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import { Route } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { RequireRole } from './RequireRole'

vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function renderComo(role: 'admin' | 'user' | null, pendiente = false) {
  vi.mocked(useCurrentUser).mockReturnValue({
    data: role ? { id: 1, name: 'Ana', email: 'a@hrms.test', role } : undefined,
    isPending: pendiente,
  } as ReturnType<typeof useCurrentUser>)
  renderRoutes(<Route path="/" element={<RequireRole admin={<p>Vista admin</p>} colaborador={<p>Vista colaborador</p>} />} />, '/')
}

describe('RequireRole de actividades', () => {
  it('el Administrador ve su vista y no la del colaborador', () => {
    renderComo('admin')

    expect(screen.getByText('Vista admin')).toBeInTheDocument()
    expect(screen.queryByText('Vista colaborador')).not.toBeInTheDocument()
  })

  it('el Colaborador ve su vista y no la del administrador', () => {
    renderComo('user')

    expect(screen.getByText('Vista colaborador')).toBeInTheDocument()
    expect(screen.queryByText('Vista admin')).not.toBeInTheDocument()
  })

  it('mientras carga la sesión no muestra ninguna vista', () => {
    renderComo(null, true)

    expect(screen.getByRole('status', { name: 'Cargando actividades' })).toBeInTheDocument()
    expect(screen.queryByText(/Vista/)).not.toBeInTheDocument()
  })

  it('sin sesión cargada muestra que no hay permisos', () => {
    renderComo(null)

    expect(screen.getByRole('alert')).toHaveTextContent('No tienes permisos para ver esta pantalla')
    expect(screen.getByRole('link', { name: 'Volver al inicio' })).toHaveAttribute('href', '/')
  })
})
