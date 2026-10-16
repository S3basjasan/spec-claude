// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { accion, paginaColaboradores, paginaHistorial, plan, puede } from '../../../../test/planFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { buscarColaboradores, cambiarParticipantes, cancelarAccion, listarHistorial, obtenerPlan } from '../api'
import { PlanDetallePage } from './PlanDetallePage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPlan: vi.fn(),
  cambiarParticipantes: vi.fn(),
  cancelarAccion: vi.fn(),
  buscarColaboradores: vi.fn(),
  listarHistorial: vi.fn(),
}))

function renderPage() {
  return renderRoutes(<Route path="/plan-formacion/:id" element={<PlanDetallePage />} />, '/plan-formacion/1')
}

const aprobado = () => plan({ estado: 'Aprobado', puede: puede({ iniciar: true, cambiar_participantes: true, cancelar_acciones: true }) })

beforeEach(() => {
  vi.mocked(obtenerPlan).mockReset().mockResolvedValue(aprobado())
  vi.mocked(cambiarParticipantes).mockReset().mockResolvedValue({ accion: accion(), avisos_cruce: [] })
  vi.mocked(cancelarAccion).mockReset().mockResolvedValue(accion({ cancelada: true }))
  vi.mocked(buscarColaboradores).mockReset().mockResolvedValue(paginaColaboradores())
  vi.mocked(listarHistorial).mockReset().mockResolvedValue(paginaHistorial([]))
})

describe('PlanDetallePage', () => {
  it('muestra el encabezado, el estado y los botones del ciclo de vida que habilita el servidor', async () => {
    renderPage()

    expect(await screen.findByRole('heading', { level: 1, name: 'Plan técnico 2026' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Iniciar ejecución: Plan técnico 2026' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Ver detalle/ })).not.toBeInTheDocument()
  })

  it('oculta cambiar participantes y cancelar cuando el estado del plan no los permite', async () => {
    vi.mocked(obtenerPlan).mockResolvedValue(plan({ estado: 'Revision', puede: puede() }))
    renderPage()

    await screen.findByRole('heading', { level: 1, name: 'Plan técnico 2026' })
    expect(screen.queryByRole('button', { name: /Cambiar participantes|Cancelar acción/ })).not.toBeInTheDocument()
  })

  it('no ofrece botones en la acción ya cancelada', async () => {
    vi.mocked(obtenerPlan).mockResolvedValue(plan({ estado: 'Aprobado', puede: puede({ cambiar_participantes: true, cancelar_acciones: true }), acciones: [accion({ cancelada: true, estado_accion: 'Cancelada', motivo_cancelacion: 'Sin cupo' })] }))
    renderPage()

    expect(await screen.findByText('Motivo de la cancelación: Sin cupo')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Cambiar participantes|Cancelar acción/ })).not.toBeInTheDocument()
  })

  it('cambia los participantes respetando la capacidad', async () => {
    vi.mocked(obtenerPlan).mockResolvedValue(plan({ estado: 'Ejecutando', puede: puede({ cambiar_participantes: true }), acciones: [accion({ capacidad_maxima: 3 })] }))
    renderPage()

    await userEvent.click(await screen.findByRole('button', { name: 'Cambiar participantes: Análisis con SQL' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Participantes de «Análisis con SQL»' })
    expect(within(dialogo).getByText('Capacidad usada: 2/3')).toBeInTheDocument()

    await userEvent.click(await within(dialogo).findByRole('button', { name: 'Agregar participante: Eva Ríos' }))
    expect(within(dialogo).getByText(/Capacidad usada: 3\/3/)).toHaveTextContent('Capacidad completa')
    expect(within(dialogo).getByRole('searchbox', { name: 'Buscar colaborador' })).toBeDisabled()
    await userEvent.click(within(dialogo).getByRole('button', { name: 'Quitar a Luis Mora' }))
    await userEvent.click(within(dialogo).getByRole('button', { name: 'Guardar participantes' }))

    await waitFor(() => expect(cambiarParticipantes).toHaveBeenCalledWith(7, [10, 12]))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('muestra los avisos de cruce devueltos al guardar sin impedir el cambio', async () => {
    vi.mocked(cambiarParticipantes).mockResolvedValue({ accion: accion(), avisos_cruce: [{ colaborador_id: 10, nombre: 'Ana Pérez', accion_id: 7, con_accion_id: 9, con_plan_id: 2 }] })
    renderPage()

    await userEvent.click(await screen.findByRole('button', { name: 'Cambiar participantes: Análisis con SQL' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Guardar participantes' }))

    expect(await screen.findByText('Ana Pérez tiene acciones con fechas que se cruzan.')).toBeInTheDocument()
  })

  it('muestra el error de capacidad del servidor sin cerrar el editor', async () => {
    vi.mocked(cambiarParticipantes).mockRejectedValue(httpError(422, { message: 'x', code: 'cupo_excedido' }))
    renderPage()

    await userEvent.click(await screen.findByRole('button', { name: 'Cambiar participantes: Análisis con SQL' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Guardar participantes' }))

    expect(await screen.findByText('Los participantes superan la capacidad máxima de la acción.')).toBeInTheDocument()
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('cancela una acción pidiendo un motivo e informando el efecto sobre los participantes', async () => {
    renderPage()

    await userEvent.click(await screen.findByRole('button', { name: 'Cancelar acción: Análisis con SQL' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Cancelar la acción: Análisis con SQL' })
    expect(within(dialogo).getAllByText(/dejarán de ver esta acción como pendiente/).length).toBeGreaterThan(0)

    await userEvent.click(within(dialogo).getByRole('button', { name: 'Cancelar acción' }))
    expect(await within(dialogo).findByText('El motivo es obligatorio.')).toBeInTheDocument()
    expect(cancelarAccion).not.toHaveBeenCalled()

    await userEvent.type(within(dialogo).getByRole('textbox', { name: 'Motivo de la cancelación' }), 'Sin cupo')
    await userEvent.click(within(dialogo).getByRole('button', { name: 'Cancelar acción' }))

    await waitFor(() => expect(cancelarAccion).toHaveBeenCalledWith(7, 'Sin cupo'))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('muestra «no existe» ante un 404 y un error con reintento ante otro fallo', async () => {
    vi.mocked(obtenerPlan).mockRejectedValueOnce(httpError(404, { message: 'x' }))
    const primero = renderPage()
    expect(await screen.findByText('El plan no existe o ya no está disponible.')).toBeInTheDocument()
    primero.unmount()

    vi.mocked(obtenerPlan).mockRejectedValueOnce(networkError())
    renderPage()
    await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByRole('heading', { level: 1, name: 'Plan técnico 2026' })).toBeInTheDocument()
  })
})
