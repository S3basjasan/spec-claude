// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerPermisos, type ClavePermiso } from '../api'
import { RequirePermiso } from './RequirePermiso'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPermisos: vi.fn(),
}))

function renderGuarda(clave: ClavePermiso, permisos: ClavePermiso[]) {
  vi.mocked(obtenerPermisos).mockResolvedValue(permisos)

  return renderRoutes(
    <Route
      path="/"
      element={
        <RequirePermiso clave={clave} fallback={<p>Sin acceso</p>}>
          <p>Contenido protegido</p>
        </RequirePermiso>
      }
    />,
    '/',
  )
}

beforeEach(() => vi.mocked(obtenerPermisos).mockReset())

describe('RequirePermiso', () => {
  it('muestra el contenido cuando el usuario tiene la clave', async () => {
    renderGuarda('desarrollo.adecuacion.ver', ['desarrollo.adecuacion.ver'])

    expect(await screen.findByText('Contenido protegido')).toBeInTheDocument()
  })

  it('muestra el fallback cuando falta la clave, aunque tenga otras', async () => {
    renderGuarda('desarrollo.adecuacion.ver', ['desarrollo.adecuacion.mapa'])

    expect(await screen.findByText('Sin acceso')).toBeInTheDocument()
    expect(screen.queryByText('Contenido protegido')).not.toBeInTheDocument()
  })

  it('mientras carga los permisos no muestra ni el contenido ni el fallback', async () => {
    let resolver: (permisos: ClavePermiso[]) => void = () => {}
    vi.mocked(obtenerPermisos).mockReturnValue(new Promise<ClavePermiso[]>((resolve) => (resolver = resolve)))
    renderRoutes(
      <Route path="/" element={<RequirePermiso clave="desarrollo.adecuacion.ver" fallback={<p>Sin acceso</p>}><p>Contenido protegido</p></RequirePermiso>} />,
      '/',
    )

    expect(screen.queryByText('Contenido protegido')).not.toBeInTheDocument()
    expect(screen.queryByText('Sin acceso')).not.toBeInTheDocument()

    resolver(['desarrollo.adecuacion.ver'])
    expect(await screen.findByText('Contenido protegido')).toBeInTheDocument()
  })
})
