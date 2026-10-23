// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { opcionesDeColaborador, opcionesDeColaboradores } from '../../../../test/adecuacionFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { buscarColaboradores, listarAdecuaciones, obtenerMiAdecuacion, obtenerOpcionesDeColaborador, obtenerPermisos, previsualizar, type ClavePermiso } from '../api'
import { EntradaAdecuacionPage } from './EntradaAdecuacionPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPermisos: vi.fn(),
  buscarColaboradores: vi.fn(),
  listarAdecuaciones: vi.fn(),
  obtenerMiAdecuacion: vi.fn(),
  obtenerOpcionesDeColaborador: vi.fn(),
  previsualizar: vi.fn(),
}))

const GESTION: ClavePermiso[] = ['desarrollo.adecuacion.generar', 'desarrollo.adecuacion.ver', 'desarrollo.adecuacion.comparar', 'desarrollo.adecuacion.mapa', 'desarrollo.adecuacion.parametros']

function render(permisos: ClavePermiso[], entrada = '/adecuacion') {
  vi.mocked(obtenerPermisos).mockResolvedValue(permisos)

  return renderRoutes(<Route path="/adecuacion" element={<EntradaAdecuacionPage />} />, entrada)
}

beforeEach(() => {
  vi.mocked(buscarColaboradores).mockReset().mockResolvedValue(opcionesDeColaboradores())
  vi.mocked(listarAdecuaciones).mockReset().mockResolvedValue({ data: [], meta: { page: 1, per_page: 15, total: 0, last_page: 1 } })
  vi.mocked(obtenerOpcionesDeColaborador).mockReset().mockResolvedValue(opcionesDeColaborador())
  vi.mocked(previsualizar).mockReset()
  vi.mocked(obtenerMiAdecuacion).mockReset().mockResolvedValue({ adecuaciones: [], seleccionada: null, aviso: 'Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.', fuentes_no_disponibles: [] })
})

describe('Entrada de Adecuación', () => {
  it('con claves de gestión muestra la página del administrador con las cinco pestañas en el orden del tablero', async () => {
    render(GESTION)

    expect(await screen.findByRole('heading', { level: 1, name: 'Adecuación a puesto futuro' })).toBeInTheDocument()
    expect(screen.getAllByRole('tab').map((t) => t.textContent)).toEqual(['Diagnóstico', 'Comparar candidatos', 'Mapa de talento', 'Evaluaciones', 'Parámetros'])
  })

  it('solo muestra las pestañas cuyo permiso tiene el usuario', async () => {
    render(['desarrollo.adecuacion.ver', 'desarrollo.adecuacion.mapa'], '/adecuacion?pestana=evaluaciones')

    expect(await screen.findByRole('heading', { level: 1, name: 'Adecuación a puesto futuro' })).toBeInTheDocument()
    expect(screen.getAllByRole('tab').map((t) => t.textContent)).toEqual(['Mapa de talento', 'Evaluaciones'])
    expect(screen.queryByRole('tab', { name: 'Parámetros' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Registrar esta evaluación' })).not.toBeInTheDocument()
  })

  it('con una pestaña no permitida en la URL abre la primera permitida', async () => {
    render(['desarrollo.adecuacion.ver'], '/adecuacion?pestana=parametros')

    expect(await screen.findByRole('tab', { name: 'Evaluaciones', selected: true })).toBeInTheDocument()
  })

  it('con solo ver_propio muestra «Mi adecuación» y ninguna pestaña de gestión', async () => {
    render(['desarrollo.adecuacion.ver_propio'])

    expect(await screen.findByRole('heading', { level: 1, name: 'Mi adecuación' })).toBeInTheDocument()
    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
    expect(buscarColaboradores).not.toHaveBeenCalled()
  })

  it('sin ninguna clave de adecuación muestra «sin acceso»', async () => {
    render([])

    expect(await screen.findByText('No tienes acceso a esta sección')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver al inicio' })).toHaveAttribute('href', '/')
  })

  it('desde el PDI, ?colaborador=&puesto= preselecciona colaborador y puesto objetivo (R13)', async () => {
    vi.mocked(obtenerOpcionesDeColaborador).mockResolvedValue(opcionesDeColaborador({ inscripciones: [] }))
    vi.mocked(previsualizar).mockReturnValue(new Promise(() => undefined))
    render(GESTION, '/adecuacion?colaborador=10&puesto=3')

    await screen.findByLabelText('Puesto objetivo')
    await waitFor(() => expect(screen.getByLabelText('Puesto objetivo')).toHaveValue('p:3'))
    expect(obtenerOpcionesDeColaborador).toHaveBeenCalledWith(10)
    await waitFor(() => expect(previsualizar).toHaveBeenCalledWith(expect.objectContaining({ colaborador_id: 10, puesto_objetivo_id: 3 })))
  })

  it('un colaborador inválido en la URL se ignora', async () => {
    render(GESTION, '/adecuacion?colaborador=abc&puesto=3')

    await screen.findByRole('heading', { level: 1, name: 'Adecuación a puesto futuro' })
    expect(obtenerOpcionesDeColaborador).not.toHaveBeenCalled()
  })
})
