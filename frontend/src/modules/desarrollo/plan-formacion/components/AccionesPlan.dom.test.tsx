// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { opcionesPlan, plan, puede } from '../../../../test/planFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { cambiarEstadoPlan, obtenerOpciones, obtenerPlan, reasignarAprobador, type Puede } from '../api'
import { AccionesPlan } from './AccionesPlan'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  cambiarEstadoPlan: vi.fn(),
  reasignarAprobador: vi.fn(),
  obtenerOpciones: vi.fn(),
  obtenerPlan: vi.fn(),
}))

function renderAcciones(permisos: Partial<Puede>, modo: 'fila' | 'detalle' = 'fila') {
  return renderRoutes(<Route path="/" element={<AccionesPlan planId={1} nombre="Plan técnico" puede={puede(permisos)} modo={modo} aprobadorId={5} />} />, '/')
}

beforeEach(() => {
  vi.mocked(cambiarEstadoPlan).mockReset().mockResolvedValue(plan())
  vi.mocked(reasignarAprobador).mockReset().mockResolvedValue(plan())
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opcionesPlan())
  vi.mocked(obtenerPlan).mockReset().mockResolvedValue(plan({ acciones: [plan().acciones[0]] }))
})

describe('AccionesPlan', () => {
  it('solo muestra los botones que habilita el servidor, siempre con «Ver detalle» en la fila', () => {
    renderAcciones({ editar: true, enviar_revision: true })

    expect(screen.getByRole('link', { name: 'Editar: Plan técnico' })).toHaveAttribute('href', '/plan-formacion/1/editar')
    expect(screen.getByRole('button', { name: 'Enviar a revisión: Plan técnico' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver detalle: Plan técnico' })).toHaveAttribute('href', '/plan-formacion/1')
    expect(screen.queryByRole('button', { name: /Aprobar|Devolver|Iniciar|Cerrar/ })).not.toBeInTheDocument()
  })

  it('en Revisión muestra Aprobar y Devolver solo si es el aprobador', () => {
    const { unmount } = renderAcciones({ aprobar: true, devolver: true })
    expect(screen.getByRole('button', { name: 'Aprobar: Plan técnico' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Devolver: Plan técnico' })).toBeInTheDocument()
    unmount()

    renderAcciones({})
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('envía a revisión sin pedir confirmación', async () => {
    renderAcciones({ enviar_revision: true })

    await userEvent.click(screen.getByRole('button', { name: 'Enviar a revisión: Plan técnico' }))

    await waitFor(() => expect(cambiarEstadoPlan).toHaveBeenCalledWith(1, 'Revision', undefined))
  })

  it('lista lo que falta cuando el plan está incompleto', async () => {
    vi.mocked(cambiarEstadoPlan).mockRejectedValue(
      httpError(422, { message: 'El plan está incompleto', code: 'plan_incompleto', faltantes: [{ accion_id: null, campo: 'aprobador' }, { accion_id: 7, campo: 'participantes' }] }),
    )
    renderAcciones({ enviar_revision: true })

    await userEvent.click(screen.getByRole('button', { name: 'Enviar a revisión: Plan técnico' }))

    const dialogo = await screen.findByRole('dialog', { name: 'No se puede enviar a revisión. Falta completar:' })
    expect(within(dialogo).getByText('Aprobador del plan')).toBeInTheDocument()
    expect(await within(dialogo).findByText('Acción «Análisis con SQL»: Al menos un participante')).toBeInTheDocument()
  })

  it.each([
    ['Aprobar', { aprobar: true }, 'Aprobado'],
    ['Iniciar ejecución', { iniciar: true }, 'Ejecutando'],
    ['Cerrar plan', { cerrar: true }, 'Cerrado'],
  ] as const)('«%s» pide confirmación antes de cambiar el estado', async (boton, permisos, destino) => {
    renderAcciones(permisos)

    await userEvent.click(screen.getByRole('button', { name: `${boton}: Plan técnico` }))
    const confirmacion = await screen.findByRole('alertdialog')
    expect(cambiarEstadoPlan).not.toHaveBeenCalled()
    await userEvent.click(within(confirmacion).getByRole('button', { name: 'Cancelar' }))
    expect(cambiarEstadoPlan).not.toHaveBeenCalled()

    await userEvent.click(screen.getByRole('button', { name: `${boton}: Plan técnico` }))
    await userEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: boton }))

    await waitFor(() => expect(cambiarEstadoPlan).toHaveBeenCalledWith(1, destino, undefined))
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
  })

  it('devolver exige un motivo y lo envía', async () => {
    renderAcciones({ devolver: true, aprobar: true })

    await userEvent.click(screen.getByRole('button', { name: 'Devolver: Plan técnico' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Devolver el plan a Borrador' })
    await userEvent.click(within(dialogo).getByRole('button', { name: 'Devolver a Borrador' }))
    expect(await within(dialogo).findByText('El motivo es obligatorio.')).toBeInTheDocument()
    expect(cambiarEstadoPlan).not.toHaveBeenCalled()

    await userEvent.type(within(dialogo).getByRole('textbox', { name: 'Motivo' }), 'Falta detallar costos')
    await userEvent.click(within(dialogo).getByRole('button', { name: 'Devolver a Borrador' }))

    await waitFor(() => expect(cambiarEstadoPlan).toHaveBeenCalledWith(1, 'Borrador', 'Falta detallar costos'))
  })

  it('muestra el error del servidor dentro del diálogo y lo deja abierto', async () => {
    vi.mocked(cambiarEstadoPlan).mockRejectedValue(httpError(403, { message: 'Solo el aprobador designado puede realizar esta acción.', code: 'usuario_no_es_aprobador' }))
    renderAcciones({ aprobar: true })

    await userEvent.click(screen.getByRole('button', { name: 'Aprobar: Plan técnico' }))
    await userEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Aprobar' }))

    expect(await screen.findByText('Solo el aprobador designado puede realizar esta acción.')).toBeInTheDocument()
    expect(screen.getByRole('alertdialog')).toBeInTheDocument()
  })

  it('en el detalle ofrece «Cambiar aprobador» y reasigna sin cambiar el estado', async () => {
    renderAcciones({ reasignar_aprobador: true }, 'detalle')
    expect(screen.queryByRole('link', { name: /Ver detalle/ })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Cambiar aprobador' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Cambiar el aprobador' })
    await userEvent.selectOptions(await within(dialogo).findByRole('combobox', { name: 'Aprobador' }), 'Esteban Rivas')
    await userEvent.click(within(dialogo).getByRole('button', { name: 'Cambiar aprobador' }))

    await waitFor(() => expect(reasignarAprobador).toHaveBeenCalledWith(1, 6))
    expect(cambiarEstadoPlan).not.toHaveBeenCalled()
  })
})
