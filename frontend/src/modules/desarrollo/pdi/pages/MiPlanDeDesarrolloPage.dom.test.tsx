// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { miPlan, pdiAcordado, pdiParaElColaborador } from '../../../../test/pdiFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { aceptarPdi, devolverDelColaborador, obtenerMiPlan } from '../api'
import { MiPlanDeDesarrolloPage } from './MiPlanDeDesarrolloPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerMiPlan: vi.fn(),
  aceptarPdi: vi.fn(),
  devolverDelColaborador: vi.fn(),
}))

function renderPagina(entrada = '/pdi') {
  return renderRoutes(<Route path="/pdi" element={<MiPlanDeDesarrolloPage />} />, entrada)
}

beforeEach(() => {
  vi.mocked(obtenerMiPlan).mockReset().mockResolvedValue(miPlan())
  vi.mocked(aceptarPdi).mockReset().mockResolvedValue(pdiAcordado())
  vi.mocked(devolverDelColaborador).mockReset().mockResolvedValue(undefined)
})

describe('Mi plan de desarrollo (Colaborador)', () => {
  it('muestra las tarjetas de sus planes con tipo, estado, avance y periodo', async () => {
    vi.mocked(obtenerMiPlan).mockResolvedValue(miPlan({ planes: [pdiAcordado({ avance: 50 }), pdiParaElColaborador({ id: 2, codigo: 'PDI-0002', tipo: 'Carrera', puesto_objetivo: { id: 2, nombre: 'Analista de datos' } })] }))
    renderPagina()

    const tarjetas = await screen.findByRole('region', { name: 'Mis planes' })
    expect(within(tarjetas).getByText('PDI-0001')).toBeInTheDocument()
    expect(within(tarjetas).getByText('PDI-0002')).toBeInTheDocument()
    expect(within(tarjetas).getByText('Acordado')).toBeInTheDocument()
    expect(within(tarjetas).getByText('50 %')).toBeInTheDocument()
    expect(within(tarjetas).getAllByText('1 jul 2026 – 31 dic 2026')).toHaveLength(2)
  })

  it('un plan Acordado no ofrece aceptar ni devolver y es de solo lectura', async () => {
    renderPagina()

    await screen.findByRole('region', { name: 'Mis planes' })
    expect(screen.queryByRole('button', { name: 'Aceptar plan' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Devolver con comentario' })).not.toBeInTheDocument()
    expect(screen.getByText('Curso de comunicación efectiva', { exact: false })).toBeInTheDocument()
  })

  it('un Borrador con el acuerdo del responsable se acepta tras marcar la lectura', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerMiPlan).mockResolvedValue(miPlan({ planes: [pdiParaElColaborador()] }))
    renderPagina()

    const aceptar = await screen.findByRole('button', { name: 'Aceptar plan' })
    expect(aceptar).toBeDisabled()
    await usuario.click(screen.getByRole('checkbox', { name: /Revisé los compromisos/ }))
    await usuario.click(aceptar)

    await waitFor(() => expect(aceptarPdi).toHaveBeenCalledWith(1, 2))
    expect(await screen.findByText('Plan aceptado. Ahora está Acordado.')).toBeInTheDocument()
  })

  it('devolver con comentario exige el texto y envía el comentario con la versión', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerMiPlan).mockResolvedValue(miPlan({ planes: [pdiParaElColaborador()] }))
    renderPagina()

    await usuario.click(await screen.findByRole('button', { name: 'Devolver con comentario' }))
    const dialogo = await screen.findByRole('dialog')
    await usuario.click(within(dialogo).getByRole('button', { name: 'Devolver plan' }))
    expect(await within(dialogo).findByText('Escribe un comentario.')).toBeInTheDocument()
    expect(devolverDelColaborador).not.toHaveBeenCalled()

    await usuario.type(within(dialogo).getByLabelText('Comentario'), 'Necesito más tiempo.')
    await usuario.click(within(dialogo).getByRole('button', { name: 'Devolver plan' }))

    await waitFor(() => expect(devolverDelColaborador).toHaveBeenCalledWith(1, 'Necesito más tiempo.', 2))
  })

  it('si el plan ya no está disponible (404) lo explica', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerMiPlan).mockResolvedValue(miPlan({ planes: [pdiParaElColaborador()] }))
    vi.mocked(aceptarPdi).mockRejectedValue({ isAxiosError: true, response: { status: 404, data: { message: 'No se encontró el plan de desarrollo.' } } })
    renderPagina()

    await usuario.click(await screen.findByRole('checkbox', { name: /Revisé los compromisos/ }))
    await usuario.click(screen.getByRole('button', { name: 'Aceptar plan' }))

    expect(await screen.findByText(/ya no está disponible/)).toBeInTheDocument()
  })

  it('«Qué sigue» lista lo próximo con fecha, destaca lo vencido y enlaza a la actividad de las acciones vinculadas', async () => {
    vi.mocked(obtenerMiPlan).mockResolvedValue(
      miPlan({
        que_sigue: [
          { tipo: 'hito', fecha: '2026-06-01', descripcion: 'Primera revisión', plan_id: 1, plan_codigo: 'PDI-0001', tipo_plan: 'Mejora', vencido: true, actividad_id: null },
          { tipo: 'accion', fecha: '2026-09-30', descripcion: 'Curso de comunicación', plan_id: 1, plan_codigo: 'PDI-0001', tipo_plan: 'Mejora', vencido: false, actividad_id: 91 },
        ],
      }),
    )
    renderPagina()

    const seccion = await screen.findByRole('region', { name: 'Qué sigue' })
    const filas = within(seccion).getAllByRole('listitem')
    expect(filas).toHaveLength(2)
    expect(within(filas[0]).getByText('Vencido')).toBeInTheDocument()
    expect(within(filas[1]).getByRole('link', { name: 'Ver actividad' })).toHaveAttribute('href', '/actividades')
  })

  it('sin planes muestra el estado vacío', async () => {
    vi.mocked(obtenerMiPlan).mockResolvedValue(miPlan({ planes: [], que_sigue: [] }))
    renderPagina()

    expect(await screen.findByText('Aún no tienes un plan de desarrollo')).toBeInTheDocument()
  })

  it('muestra el error con «Reintentar»', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerMiPlan).mockRejectedValueOnce(new Error('falla')).mockResolvedValue(miPlan())
    renderPagina()

    await usuario.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Mi plan de desarrollo' })).toBeInTheDocument()
    expect(await screen.findByRole('region', { name: 'Mis planes' })).toBeInTheDocument()
  })
})
