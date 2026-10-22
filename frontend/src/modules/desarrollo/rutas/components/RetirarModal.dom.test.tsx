// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { inscripcion } from '../../../../test/rutasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { retirarInscripcion } from '../api'
import { RetirarModal } from './RetirarModal'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  retirarInscripcion: vi.fn(),
}))

function renderModal() {
  const onCerrar = vi.fn()
  const onRetirado = vi.fn()
  renderRoutes(<Route path="/" element={<RetirarModal inscripcion={inscripcion()} rutaId={1} onCerrar={onCerrar} onRetirado={onRetirado} />} />, '/')
  return { onCerrar, onRetirado }
}

beforeEach(() => {
  vi.mocked(retirarInscripcion).mockReset().mockResolvedValue(inscripcion({ estado: 'Retirada', motivo: 'Cambió de área' }))
})

describe('RetirarModal', () => {
  it('explica el efecto y exige el motivo antes de confirmar', async () => {
    renderModal()

    expect(screen.getByRole('dialog', { name: 'Retirar de la ruta' })).toHaveTextContent('Ana Núñez dejará de participar en la ruta')
    await userEvent.click(screen.getByRole('button', { name: 'Retirar' }))

    expect(await screen.findByText('El motivo del retiro es obligatorio.')).toBeInTheDocument()
    expect(retirarInscripcion).not.toHaveBeenCalled()
  })

  it('limita el motivo a 500 caracteres', async () => {
    renderModal()

    await userEvent.click(screen.getByLabelText('Motivo del retiro'))
    await userEvent.paste('m'.repeat(501))
    await userEvent.click(screen.getByRole('button', { name: 'Retirar' }))

    expect(await screen.findByText('El motivo no puede superar los 500 caracteres.')).toBeInTheDocument()
    expect(retirarInscripcion).not.toHaveBeenCalled()
  })

  it('con motivo retira, avisa y cierra', async () => {
    const { onRetirado } = renderModal()

    await userEvent.type(screen.getByLabelText('Motivo del retiro'), '  Cambió de área  ')
    await userEvent.click(screen.getByRole('button', { name: 'Retirar' }))

    expect(retirarInscripcion).toHaveBeenCalledWith(1, 'Cambió de área')
    await vi.waitFor(() => expect(onRetirado).toHaveBeenCalledWith('Ana Núñez salió de la ruta.'))
  })

  it('bloquea el botón mientras la operación está pendiente (sin doble envío)', async () => {
    vi.mocked(retirarInscripcion).mockReturnValue(new Promise(() => {}))
    renderModal()

    await userEvent.type(screen.getByLabelText('Motivo del retiro'), 'Motivo')
    await userEvent.click(screen.getByRole('button', { name: 'Retirar' }))

    expect(screen.getByRole('button', { name: 'Retirar' })).toBeDisabled()
    expect(retirarInscripcion).toHaveBeenCalledTimes(1)
  })

  it('muestra el error del servidor sin cerrar el modal', async () => {
    vi.mocked(retirarInscripcion).mockRejectedValue(httpError(422, { message: 'La inscripción ya no está activa y no admite cambios.' }))
    const { onCerrar } = renderModal()

    await userEvent.type(screen.getByLabelText('Motivo del retiro'), 'Motivo')
    await userEvent.click(screen.getByRole('button', { name: 'Retirar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('La inscripción ya no está activa')
    expect(onCerrar).not.toHaveBeenCalled()
  })
})
