// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { opcionesDeColaboradores } from '../../../../test/adecuacionFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { buscarColaboradores, comparar, type Comparacion, type ConsultaComparacion } from '../api'
import { PestanaComparar } from './PestanaComparar'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  buscarColaboradores: vi.fn(),
  comparar: vi.fn(),
}))

const comp = { Experiencia: 0.6, Conocimiento: null, Competencia: 0.83 } as const

function respuesta(sobrescribir: Partial<Comparacion> = {}): Comparacion {
  return {
    puesto_objetivo: { id: 2, nombre: 'Analista de datos' },
    pesos: { Competencia: 40, Conocimiento: 30, Experiencia: 30 },
    ranking: [
      { posicion: 1, colaborador: { id: 11, nombre: 'Lucía Andrade' }, adecuacion_id: 7, puntaje: 91.5, cumplimiento_por_dimension: { ...comp }, preparacion: 'Lista', preparacion_etiqueta: 'Lista', brechas: 0, puesto_actual: 'Asistente' },
      { posicion: 2, colaborador: { id: 10, nombre: 'Diego Paredes' }, adecuacion_id: 6, puntaje: 73.3, cumplimiento_por_dimension: { ...comp }, preparacion: 'EnDesarrollo', preparacion_etiqueta: 'En desarrollo', brechas: 2, puesto_actual: null },
    ],
    incompletos: [{ colaborador: { id: 12, nombre: 'Marta Ríos' }, adecuacion_id: 8 }],
    sin_adecuacion: [{ colaborador: { id: 13, nombre: 'Pablo Ayala' } }],
    fuentes_no_disponibles: [],
    ...sobrescribir,
  }
}

function renderComparar(entrada = '/adecuacion?puesto=2&candidatos=10,11,12,13') {
  return renderRoutes(<Route path="/adecuacion" element={<PestanaComparar />} />, entrada)
}

beforeEach(() => {
  vi.mocked(buscarColaboradores).mockReset().mockResolvedValue(opcionesDeColaboradores())
  vi.mocked(comparar).mockReset().mockImplementation(async (c: ConsultaComparacion) => respuesta({ pesos: c.pesos === undefined ? respuesta().pesos : { Competencia: c.pesos.competencias, Conocimiento: c.pesos.conocimientos, Experiencia: c.pesos.experiencia } }))
})

describe('Comparar candidatos', () => {
  it('sin puesto ni candidatos pide elegirlos', async () => {
    renderComparar('/adecuacion')

    expect(await screen.findByText('Selecciona al menos dos candidatos y pesos que sumen 100 para ver el ranking.')).toBeInTheDocument()
    expect(comparar).not.toHaveBeenCalled()
  })

  it('muestra el ranking con puntaje, preparación y brechas, y los Incompletos y sin adecuación aparte', async () => {
    renderComparar()

    const ranking = await screen.findByRole('region', { name: 'Ranking para Analista de datos' })
    const filas = within(ranking).getAllByRole('row')
    expect(filas).toHaveLength(3)
    expect(within(filas[1]).getByText('Lucía Andrade')).toBeInTheDocument()
    expect(within(filas[1]).getByText('91.5 %')).toBeInTheDocument()
    expect(within(filas[2]).getByText('73.3 %')).toBeInTheDocument()
    expect(within(filas[2]).getByText('2 brechas')).toBeInTheDocument()
    expect(within(filas[1]).getByRole('link', { name: 'Ver diagnóstico de Lucía Andrade' })).toHaveAttribute('href', '/adecuacion/evaluaciones/7')

    const incompletos = screen.getByRole('region', { name: 'Preparación incompleta (sin puntaje)' })
    expect(within(incompletos).getByText('Marta Ríos')).toBeInTheDocument()
    expect(within(incompletos).queryByText(/%/)).not.toBeInTheDocument()
    expect(within(screen.getByRole('region', { name: 'Sin adecuación para este puesto' })).getByText('Pablo Ayala')).toBeInTheDocument()
  })

  it('muestra los pesos guardados y la suma en el orden del tablero', async () => {
    renderComparar()

    await screen.findByRole('region', { name: /Ranking/ })

    await waitFor(() => expect(screen.getByLabelText('Experiencia (%)')).toHaveValue(30))
    expect(screen.getByLabelText('Conocim. (%)')).toHaveValue(30)
    expect(screen.getByLabelText('Compet. (%)')).toHaveValue(40)
    expect(screen.getByText('Suma: 100 %')).toBeInTheDocument()
  })

  it('con pesos que no suman 100 lo avisa y no consulta', async () => {
    const usuario = userEvent.setup()
    renderComparar()
    await screen.findByRole('region', { name: /Ranking/ })
    const llamadas = vi.mocked(comparar).mock.calls.length

    const experiencia = screen.getByLabelText('Experiencia (%)')
    await usuario.clear(experiencia)
    await usuario.type(experiencia, '50')

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Suma: 120 %. Los pesos deben sumar 100.'))
    expect(vi.mocked(comparar).mock.calls.slice(llamadas).filter(([c]) => c.pesos !== undefined)).toHaveLength(0)
  })

  it('con pesos válidos los envía solo para la consulta', async () => {
    const usuario = userEvent.setup()
    renderComparar('/adecuacion?puesto=2&candidatos=10,11&pe=50&pk=0&pc=50')

    await waitFor(() => expect(comparar).toHaveBeenCalledWith({ puesto_objetivo_id: 2, colaborador_ids: [10, 11], pesos: { competencias: 50, conocimientos: 0, experiencia: 50 } }))
    await usuario.click(await screen.findByRole('button', { name: 'Usar los pesos guardados' }))

    await waitFor(() => expect(screen.queryByRole('button', { name: 'Usar los pesos guardados' })).not.toBeInTheDocument())
  })

  it('permite elegir y quitar candidatos y los guarda en la URL', async () => {
    const usuario = userEvent.setup()
    renderComparar('/adecuacion?puesto=2')

    expect(await screen.findByText('Candidatos (0)')).toBeInTheDocument()
    await usuario.click(await screen.findByRole('button', { name: 'Diego Paredes' }))
    await usuario.click(screen.getByRole('button', { name: 'Lucía Andrade' }))

    expect(screen.getByText('Candidatos (2)')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Diego Paredes' })).toHaveAttribute('aria-pressed', 'true')
    await waitFor(() => expect(comparar).toHaveBeenCalledWith({ puesto_objetivo_id: 2, colaborador_ids: [10, 11] }))

    await usuario.click(screen.getByRole('button', { name: 'Diego Paredes' }))
    expect(screen.getByText('Candidatos (1)')).toBeInTheDocument()
  })

  it('explica los errores de negocio y permite reintentar los demás', async () => {
    vi.mocked(comparar).mockRejectedValueOnce(new Error('red')).mockResolvedValue(respuesta())
    const usuario = userEvent.setup()
    renderComparar()

    await usuario.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('region', { name: /Ranking/ })).toBeInTheDocument()
  })
})
