// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { generarPlan } from '../api'
import { BotonGenerarPlan } from './BotonGenerarPlan'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  generarPlan: vi.fn(),
}))

function renderBoton(props: Partial<Parameters<typeof BotonGenerarPlan>[0]> = {}) {
  return renderRoutes(<Route path="/dnf" element={<BotonGenerarPlan dnfId={7} cantidadBrechas={3} plan={null} {...props} />} />, '/dnf')
}

beforeEach(() => {
  vi.mocked(generarPlan).mockReset().mockResolvedValue({
    plan_formacion_id: 31,
    generado_por: { id: 1, nombre: 'Marta Ríos' },
    generado_en: '2026-10-15T15:00:00+00:00',
  })
})

describe('BotonGenerarPlan', () => {
  it('genera el borrador y avisa al padre con la referencia del plan', async () => {
    const onGenerado = vi.fn()
    renderBoton({ onGenerado })

    await userEvent.click(screen.getByRole('button', { name: 'Generar Plan de Formación' }))

    await waitFor(() => expect(onGenerado).toHaveBeenCalledWith(expect.objectContaining({ plan_formacion_id: 31 })))
    expect(generarPlan).toHaveBeenCalledWith(7)
  })

  it('sin brechas queda deshabilitado con el motivo visible y enlazado', () => {
    renderBoton({ cantidadBrechas: 0 })

    const boton = screen.getByRole('button', { name: 'Generar Plan de Formación' })
    expect(boton).toBeDisabled()
    expect(boton).toHaveAccessibleDescription('La DNF no tiene brechas: no hay nada que planificar.')
  })

  it('con un plan ya generado no ofrece el botón y muestra el enlace al plan, quién y cuándo', () => {
    renderBoton({ plan: { id: 31, generado_por: { id: 1, nombre: 'Marta Ríos' }, generado_en: '2026-10-15T15:00:00+00:00' } })

    expect(screen.queryByRole('button', { name: 'Generar Plan de Formación' })).not.toBeInTheDocument()
    expect(screen.getByText(/Esta DNF ya generó un plan de formación\./)).toBeInTheDocument()
    expect(screen.getByText(/Plan generado por Marta Ríos/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver plan' })).toHaveAttribute('href', '/plan-formacion/31')
  })

  it('si Plan de Formación no está disponible muestra el mensaje y permite reintentar sin reintentos automáticos', async () => {
    vi.mocked(generarPlan).mockRejectedValueOnce(httpError(503, { message: 'Plan de Formación no disponible.' }))
    renderBoton()

    await userEvent.click(screen.getByRole('button', { name: 'Generar Plan de Formación' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Plan de Formación no disponible. Inténtalo más tarde.')
    expect(generarPlan).toHaveBeenCalledTimes(1)

    await userEvent.click(screen.getByRole('button', { name: 'Generar Plan de Formación' }))
    await waitFor(() => expect(generarPlan).toHaveBeenCalledTimes(2))
  })

  it('muestra el mensaje del servidor ante un 422', async () => {
    vi.mocked(generarPlan).mockRejectedValue(httpError(422, { message: 'La DNF ya generó un plan.' }))
    renderBoton()

    await userEvent.click(screen.getByRole('button', { name: 'Generar Plan de Formación' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('La DNF ya generó un plan.')
  })

  it('bloquea el botón mientras se genera', async () => {
    vi.mocked(generarPlan).mockReturnValue(new Promise(() => {}))
    renderBoton()

    await userEvent.click(screen.getByRole('button', { name: 'Generar Plan de Formación' }))

    expect(await screen.findByRole('button', { name: 'Generando…' })).toBeDisabled()
  })
})
