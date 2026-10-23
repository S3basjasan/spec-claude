// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { opcionesDeColaboradores } from '../../../../test/adecuacionFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { buscarColaboradores, obtenerMapa, type FilaDeMapa, type FiltrosMapa, type Mapa } from '../api'
import { PestanaMapa } from './PestanaMapa'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  buscarColaboradores: vi.fn(),
  obtenerMapa: vi.fn(),
}))

const cumpl = { Experiencia: 0.6, Conocimiento: null, Competencia: 0.83 } as const

function fila(sobrescribir: Partial<FilaDeMapa> = {}): FilaDeMapa {
  return {
    adecuacion_id: 1,
    codigo: 'ADE-0001',
    colaborador: { id: 10, nombre: 'Diego Paredes', departamento: 'Tecnología' },
    ruta: { id: 1, nombre: 'Carrera de especialista' },
    puesto_actual: { id: 1, nombre: 'Asistente de datos' },
    puesto_objetivo: { id: 2, nombre: 'Analista de datos' },
    preparacion: 'NoLista',
    preparacion_etiqueta: 'No lista',
    cumplimiento_por_dimension: { ...cumpl },
    brechas: 3,
    tiempo_maximo_cierre_meses: 18,
    ...sobrescribir,
  }
}

const filas = [
  fila(),
  fila({ adecuacion_id: 2, colaborador: { id: 11, nombre: 'Lucía Andrade', departamento: 'Talento Humano' }, ruta: null, puesto_actual: null, preparacion: 'Lista', preparacion_etiqueta: 'Lista', brechas: 0, tiempo_maximo_cierre_meses: null }),
]

function mapa(items: FilaDeMapa[], page = 1): Mapa {
  return { data: items, kpis: { Lista: 1, EnDesarrollo: 0, NoLista: 1, Incompleta: 0 }, meta: { page, per_page: 15, total: items.length, last_page: 1 }, fuentes_no_disponibles: [] }
}

function renderMapa(entrada = '/adecuacion?pestana=mapa') {
  return renderRoutes(<Route path="/adecuacion" element={<PestanaMapa />} />, entrada)
}

beforeEach(() => {
  vi.mocked(buscarColaboradores).mockReset().mockResolvedValue(opcionesDeColaboradores())
  vi.mocked(obtenerMapa).mockReset().mockImplementation(async (f: FiltrosMapa) => mapa(f.preparacion ? filas.filter((x) => x.preparacion === f.preparacion) : filas))
})

describe('Mapa de talento', () => {
  it('muestra los cuatro indicadores y la tabla con todas las columnas', async () => {
    renderMapa()

    const kpis = await screen.findByRole('list', { name: 'Distribución por preparación' })
    expect(within(kpis).getAllByRole('listitem')).toHaveLength(4)
    expect(within(kpis).getByText('Alguna brecha supera el umbral')).toBeInTheDocument()

    const tabla = screen.getByRole('region', { name: 'Mapa de talento' })
    const encabezados = within(tabla).getAllByRole('columnheader').map((h) => h.textContent)
    expect(encabezados).toEqual(['Colaborador', 'Ruta', 'Puesto actual → objetivo', 'Exp. / Conoc. / Comp.', 'Preparación', 'Brechas', 'Tiempo máx. (meses)', 'Programa de Talentos', 'Acciones'])
  })

  it('cada fila muestra puesto actual → objetivo, porcentajes por dimensión, brechas y tiempo máximo', async () => {
    renderMapa()

    const filaDeDiego = (await screen.findByText('Diego Paredes')).closest('tr') as HTMLElement
    expect(within(filaDeDiego).getByText('Asistente de datos → Analista de datos')).toBeInTheDocument()
    expect(within(filaDeDiego).getByText('60 % / — / 83 %')).toBeInTheDocument()
    expect(within(filaDeDiego).getByText('No lista')).toBeInTheDocument()
    expect(within(filaDeDiego).getByText('18')).toBeInTheDocument()
    expect(within(filaDeDiego).getByText('Carrera de especialista')).toBeInTheDocument()

    const filaDeLucia = screen.getByText('Lucía Andrade').closest('tr') as HTMLElement
    expect(within(filaDeLucia).getByText('Puesto directo')).toBeInTheDocument()
    expect(within(filaDeLucia).getByText('Sin puesto actual → Analista de datos')).toBeInTheDocument()
    expect(within(filaDeLucia).getByText('—', { selector: 'td.tabular-nums' })).toBeInTheDocument()
  })

  it('«Diagnóstico» abre el detalle e «Incluir» lleva al asistente del PDI con brechas y se deshabilita sin ellas', async () => {
    renderMapa()

    expect(await screen.findByRole('link', { name: 'Ver diagnóstico de Diego Paredes' })).toHaveAttribute('href', '/adecuacion/evaluaciones/1')
    const incluir = screen.getByRole('link', { name: 'Incluir a Diego Paredes en el Programa de Talentos' })
    expect(incluir.getAttribute('href')).toMatch(/^\/pdi\/nuevo\?colaborador=\d+&tipo=Talento&puesto_objetivo=\d+$/)
  })

  it('los filtros viven en la URL y «Limpiar filtros» los quita', async () => {
    const usuario = userEvent.setup()
    renderMapa('/adecuacion?pestana=mapa&preparacion=NoLista')

    await waitFor(() => expect(obtenerMapa).toHaveBeenCalledWith(expect.objectContaining({ preparacion: 'NoLista', page: 1 })))
    expect(await screen.findByLabelText('Preparación')).toHaveValue('NoLista')
    await usuario.click(screen.getByRole('button', { name: 'Limpiar filtros' }))

    await waitFor(() => expect(obtenerMapa).toHaveBeenLastCalledWith(expect.not.objectContaining({ preparacion: 'NoLista' })))
    expect(screen.queryByRole('button', { name: 'Limpiar filtros' })).not.toBeInTheDocument()
  })

  it('filtra por departamento y ruta con las opciones del servidor', async () => {
    const usuario = userEvent.setup()
    renderMapa()
    await screen.findByText('Diego Paredes')

    await usuario.selectOptions(await screen.findByLabelText('Departamento'), '2')
    await usuario.selectOptions(screen.getByLabelText('Ruta'), '1')

    await waitFor(() => expect(obtenerMapa).toHaveBeenLastCalledWith(expect.objectContaining({ departamento_id: 2, ruta_id: 1 })))
  })

  it('sin coincidencias lo dice y sin adecuaciones muestra el vacío inicial', async () => {
    vi.mocked(obtenerMapa).mockResolvedValue(mapa([]))
    const { unmount } = renderMapa()
    expect(await screen.findByText('Aún no hay adecuaciones')).toBeInTheDocument()
    unmount()

    renderMapa('/adecuacion?pestana=mapa&departamento=2')
    expect(await screen.findByText('Ninguna persona coincide con los filtros')).toBeInTheDocument()
  })

  it('un fallo de carga permite reintentar', async () => {
    vi.mocked(obtenerMapa).mockRejectedValueOnce(new Error('red')).mockResolvedValue(mapa(filas))
    const usuario = userEvent.setup()
    renderMapa()

    await usuario.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Diego Paredes')).toBeInTheDocument()
  })
})
