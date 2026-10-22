// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerPermisos } from '../api'
import { RequirePermiso } from './RequirePermiso'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPermisos: vi.fn(),
}))

function renderPermiso() {
  return renderRoutes(
    <Route
      path="/"
      element={
        <RequirePermiso clave="desarrollo.rutas.gestionar" fallback={<p>Sin permiso</p>}>
          <button type="button">Nueva ruta</button>
        </RequirePermiso>
      }
    />,
    '/',
  )
}

beforeEach(() => {
  vi.mocked(obtenerPermisos).mockReset()
})

describe('RequirePermiso', () => {
  it('muestra el contenido cuando el usuario tiene la clave', async () => {
    vi.mocked(obtenerPermisos).mockResolvedValue(['desarrollo.rutas.ver', 'desarrollo.rutas.gestionar'])
    renderPermiso()

    expect(await screen.findByRole('button', { name: 'Nueva ruta' })).toBeInTheDocument()
    expect(screen.queryByText('Sin permiso')).not.toBeInTheDocument()
  })

  it('oculta el contenido y muestra el fallback cuando falta la clave', async () => {
    vi.mocked(obtenerPermisos).mockResolvedValue(['desarrollo.rutas.ver_propio'])
    renderPermiso()

    expect(await screen.findByText('Sin permiso')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Nueva ruta' })).not.toBeInTheDocument()
  })

  it('mientras llegan los permisos no muestra las acciones restringidas', () => {
    vi.mocked(obtenerPermisos).mockReturnValue(new Promise(() => {}))
    renderPermiso()

    expect(screen.queryByRole('button', { name: 'Nueva ruta' })).not.toBeInTheDocument()
  })
})
