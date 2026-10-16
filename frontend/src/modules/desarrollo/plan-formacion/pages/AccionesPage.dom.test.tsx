// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { networkError } from '../../../../test/apiErrors'
import { filaAccion, opcionesPlan, paginaAcciones } from '../../../../test/planFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarAcciones, obtenerOpciones } from '../api'
import { AccionesPage } from './AccionesPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarAcciones: vi.fn(),
  obtenerOpciones: vi.fn(),
}))

function renderPage(entrada = '/plan-formacion/acciones?mes=2026-10') {
  return renderRoutes(<Route path="/plan-formacion/acciones" element={<AccionesPage />} />, entrada)
}

const filas = [
  filaAccion(),
  filaAccion({ id: 8, nombre: 'Taller cancelado', cancelada: true, estado_accion: 'Cancelada', tipo: 'Mentoring', plan: { id: 1, codigo: 'PLAN-0001', nombre: 'Plan técnico 2026', estado: 'Ejecutando' } }),
]

beforeEach(() => {
  vi.mocked(listarAcciones).mockReset().mockResolvedValue(paginaAcciones(filas))
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opcionesPlan())
})

describe('AccionesPage', () => {
  it('abre en el Calendario, pide la rejilla visible del mes y muestra el resumen', async () => {
    renderPage()

    expect(await screen.findByRole('table', { name: 'Calendario de Octubre 2026' })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Calendario' })).toHaveAttribute('aria-selected', 'true')
    expect(listarAcciones).toHaveBeenCalledWith({ fecha_desde: '2026-09-28', fecha_hasta: '2026-11-01', per_page: 200 })
    expect(await screen.findByText('2 acciones')).toBeInTheDocument()
  })

  it('en la Lista muestra las columnas, el badge del plan y «Cancelada» en las canceladas', async () => {
    renderPage('/plan-formacion/acciones?vista=lista')

    const tabla = await screen.findByRole('table', { name: 'Acciones formativas' })
    const filasTabla = within(tabla).getAllByRole('row')
    expect(within(filasTabla[1]).getByText('PLAN-0001')).toBeInTheDocument()
    expect(within(filasTabla[1]).getByText('Aprobado')).toBeInTheDocument()
    expect(within(filasTabla[1]).getByText('18/20')).toBeInTheDocument()
    expect(within(filasTabla[2]).getByText('Ejecutando')).toBeInTheDocument()
    expect(within(filasTabla[2]).getByText('Cancelada')).toBeInTheDocument()
    expect(listarAcciones).toHaveBeenCalledWith({ page: 1, per_page: 15 })
  })

  it('al cambiar de pestaña conserva los filtros', async () => {
    renderPage('/plan-formacion/acciones?vista=lista&estado=Aprobado&tipo=Virtual&competencia=1')
    await screen.findByRole('table', { name: 'Acciones formativas' })

    await userEvent.click(screen.getByRole('tab', { name: 'Calendario' }))

    await waitFor(() => expect(screen.getByTestId('ubicacion')).not.toHaveTextContent('vista=lista'))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('estado=Aprobado')
    expect(screen.getByRole('combobox', { name: 'Estado del plan' })).toHaveValue('Aprobado')
    expect(screen.getByRole('combobox', { name: 'Tipo' })).toHaveValue('Virtual')
    await waitFor(() => expect(listarAcciones).toHaveBeenLastCalledWith(expect.objectContaining({ estado: 'Aprobado', tipo: 'Virtual', competencia_id: 1, per_page: 200 })))
  })

  it('acota el calendario al rango de fechas elegido y valida un rango invertido', async () => {
    renderPage('/plan-formacion/acciones?mes=2026-10&desde=2026-10-10&hasta=2026-10-15')

    await waitFor(() => expect(listarAcciones).toHaveBeenCalledWith({ fecha_desde: '2026-10-10', fecha_hasta: '2026-10-15', per_page: 200 }))

    vi.mocked(listarAcciones).mockClear()
    const invertido = renderPage('/plan-formacion/acciones?desde=2026-10-20&hasta=2026-10-01')
    expect(await screen.findAllByText('La fecha inicial no puede ser posterior a la final.')).not.toHaveLength(0)
    expect(listarAcciones).not.toHaveBeenCalled()
    invertido.unmount()
  })

  it('abre el detalle de la acción al hacer clic en el calendario', async () => {
    renderPage()

    await userEvent.click((await screen.findAllByRole('button', { name: 'Análisis con SQL, Virtual' }))[0])

    const dialogo = await screen.findByRole('dialog', { name: 'Análisis con SQL' })
    expect(within(dialogo).getByText('Ing. Vera')).toBeInTheDocument()
    expect(within(dialogo).getByText('18/20')).toBeInTheDocument()
    expect(within(dialogo).getByRole('link', { name: 'Ver plan' })).toHaveAttribute('href', '/plan-formacion/1')
  })

  it('muestra el estado vacío y el de error con reintento', async () => {
    vi.mocked(listarAcciones).mockResolvedValue(paginaAcciones([]))
    const vacio = renderPage('/plan-formacion/acciones?vista=lista')
    expect(await screen.findByText('No hay acciones para mostrar')).toBeInTheDocument()
    vacio.unmount()

    vi.mocked(listarAcciones).mockRejectedValueOnce(networkError()).mockResolvedValue(paginaAcciones(filas))
    renderPage('/plan-formacion/acciones?vista=lista')
    await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByRole('table', { name: 'Acciones formativas' })).toBeInTheDocument()
  })

  it('«Limpiar filtros» conserva la pestaña', async () => {
    renderPage('/plan-formacion/acciones?vista=lista&estado=Aprobado')
    await screen.findByRole('table', { name: 'Acciones formativas' })

    await userEvent.click(screen.getByRole('button', { name: 'Limpiar filtros' }))

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('/plan-formacion/acciones?vista=lista'))
    expect(screen.getByTestId('ubicacion')).not.toHaveTextContent('estado=')
  })
})
