// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { hito, opcionesGenerales, resumen } from '../../../../test/pdiFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import type { ClavePermiso, FilaDeGantt, FilaSinPlan, Gantt, PaginaDePlanes } from '../api'
import { listarPdi, listarSinPlan, obtenerGantt, obtenerOpciones, obtenerPermisos } from '../api'
import { ListaDePdiPage } from './ListaDePdiPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPermisos: vi.fn(),
  obtenerOpciones: vi.fn(),
  listarPdi: vi.fn(),
  listarSinPlan: vi.fn(),
  obtenerGantt: vi.fn(),
}))

const TODOS: ClavePermiso[] = ['desarrollo.pdi.gestionar', 'desarrollo.pdi.ver', 'desarrollo.pdi.gantt']

function pagina(planes = [resumen()], sobrescribir: Partial<PaginaDePlanes['meta']> = {}): PaginaDePlanes {
  return {
    data: planes,
    meta: { page: 1, per_page: 15, total: planes.length, last_page: 1, kpis: { borrador: 1, acordado: 2, cerrado: 3, hitos_vencidos: 4 }, ...sobrescribir },
  } as PaginaDePlanes
}

function sinPlan(filas: FilaSinPlan[] = []) {
  return { data: filas, meta: { page: 1, per_page: 5, total: filas.length, last_page: 1 } }
}

function filaGantt(): FilaDeGantt {
  return {
    plan: { id: 1, codigo: 'PDI-0001', colaborador: { id: 10, nombre: 'Diego Paredes', departamento: 'Tecnología' }, tipo: 'Mejora', estado: 'Acordado', responsable: { id: 1, nombre: 'Marta Ríos', sin_permiso: false }, fecha_inicio: '2026-07-01', fecha_fin: '2026-12-31', avance: 40, hitos_vencidos: 0 },
    acciones: [{ id: 100, compromiso: 'Comunicación efectiva', descripcion: 'Curso de comunicación', tipo: 'Curso', fecha_inicio: '2026-07-15', fecha_fin: '2026-09-30', estado: 'EnCurso' }],
    hitos: [hito()],
  }
}

function renderLista(entrada = '/pdi') {
  return renderRoutes(<Route path="/pdi" element={<ListaDePdiPage />} />, entrada)
}

beforeEach(() => {
  vi.mocked(obtenerPermisos).mockReset().mockResolvedValue(TODOS)
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opcionesGenerales())
  vi.mocked(listarPdi).mockReset().mockResolvedValue(pagina())
  vi.mocked(listarSinPlan).mockReset().mockResolvedValue(sinPlan())
  vi.mocked(obtenerGantt)
    .mockReset()
    .mockResolvedValue({ data: [filaGantt()], meta: { page: 1, per_page: 10, total: 1, last_page: 1 }, hoy: '2026-09-01' } as Gantt)
})

describe('Lista de PDI (administrador)', () => {
  it('muestra los indicadores y la tabla con código, colaborador, estado y enlace al detalle', async () => {
    renderLista()

    expect(await screen.findByRole('heading', { level: 1, name: 'Plan de Desarrollo Individual (PDI)' })).toBeInTheDocument()
    const tabla = await screen.findByRole('table')
    expect(within(tabla).getByText('Diego Paredes')).toBeInTheDocument()
    expect(within(tabla).getByText('Borrador')).toBeInTheDocument()
    expect(within(tabla).getByRole('link', { name: 'Abrir PDI-0001' })).toHaveAttribute('href', '/pdi/1')
    const kpis = await screen.findByRole('region', { name: 'Plan de Desarrollo Individual (PDI)' })
    expect(within(kpis).getByText('Hitos vencidos')).toBeInTheDocument()
    expect(within(kpis).getByText('4')).toBeInTheDocument()
  })

  it('los filtros se aplican y se guardan en la consulta; «Limpiar filtros» los quita', async () => {
    const usuario = userEvent.setup()
    renderLista()

    await usuario.selectOptions(await screen.findByLabelText('Tipo'), 'Carrera')
    await usuario.selectOptions(screen.getByLabelText('Estado'), 'Acordado')

    await waitFor(() => expect(listarPdi).toHaveBeenLastCalledWith(expect.objectContaining({ tipo: 'Carrera', estado: 'Acordado' })))
    await usuario.click(screen.getByRole('button', { name: 'Limpiar filtros' }))
    await waitFor(() => expect(vi.mocked(listarPdi).mock.lastCall?.[0]?.tipo).toBeUndefined())
  })

  it('sin resultados muestra el estado vacío', async () => {
    vi.mocked(listarPdi).mockResolvedValue(pagina([]))
    renderLista()

    expect(await screen.findByText('Ningún PDI coincide con los filtros')).toBeInTheDocument()
  })

  it('el error se puede reintentar', async () => {
    const usuario = userEvent.setup()
    vi.mocked(listarPdi).mockRejectedValueOnce(new Error('falla')).mockResolvedValue(pagina())
    renderLista()

    await usuario.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('table')).toBeInTheDocument()
  })

  it('«Personas con brecha y sin PDI» enlaza al asistente con colaborador, tipo y puesto objetivo', async () => {
    vi.mocked(listarSinPlan).mockResolvedValue(
      sinPlan([
        { colaborador: { id: 11, nombre: 'Lucía Andrade', departamento: 'Talento Humano' }, origen: 'adecuacion', tipo_sugerido: 'Carrera', puesto_objetivo: { id: 3, nombre: 'Analista senior' }, brechas: 2 },
      ]),
    )
    renderLista()

    const crear = await screen.findByRole('link', { name: 'Crear PDI: Lucía Andrade' })

    expect(crear).toHaveAttribute('href', '/pdi/nuevo?colaborador=11&tipo=Carrera&puesto_objetivo=3')
  })

  it('sin la clave de gestión no hay «Nuevo PDI» ni bloque de colaboradores sin plan', async () => {
    vi.mocked(obtenerPermisos).mockResolvedValue(['desarrollo.pdi.ver'])
    renderLista()

    await screen.findByRole('table')
    expect(screen.queryByRole('link', { name: 'Nuevo PDI' })).not.toBeInTheDocument()
    expect(screen.queryByText('Personas con brecha y sin PDI')).not.toBeInTheDocument()
    expect(listarSinPlan).not.toHaveBeenCalled()
  })

  it('con la clave de gestión ofrece «Nuevo PDI»', async () => {
    renderLista()

    expect(await screen.findByRole('link', { name: 'Nuevo PDI' })).toHaveAttribute('href', '/pdi/nuevo')
  })
})

describe('Seguimiento (Gantt)', () => {
  it('muestra la pestaña solo con la clave del Gantt y carga las acciones de los planes Acordados', async () => {
    const usuario = userEvent.setup()
    renderLista()

    await usuario.click(await screen.findByRole('tab', { name: 'Seguimiento (Gantt)' }))

    expect(await screen.findByText('Seguimiento de acciones de los PDI Acordados')).toBeInTheDocument()
    expect(await screen.findAllByText(/Curso de comunicación/)).not.toHaveLength(0)
    expect(screen.queryByLabelText('Fin desde')).not.toBeInTheDocument()
  })

  it('sin la clave del Gantt no existe la pestaña', async () => {
    vi.mocked(obtenerPermisos).mockResolvedValue(['desarrollo.pdi.ver'])
    renderLista()

    await screen.findByRole('table')
    expect(screen.queryByRole('tab', { name: 'Seguimiento (Gantt)' })).not.toBeInTheDocument()
  })

  it('la pestaña se abre desde la URL y sin planes Acordados muestra el estado vacío', async () => {
    vi.mocked(obtenerGantt).mockResolvedValue({ data: [], meta: { page: 1, per_page: 10, total: 0, last_page: 1 }, hoy: '2026-09-01' } as Gantt)
    renderLista('/pdi?pestana=gantt')

    expect(await screen.findByText('No hay PDI Acordados con estos filtros')).toBeInTheDocument()
  })
})
