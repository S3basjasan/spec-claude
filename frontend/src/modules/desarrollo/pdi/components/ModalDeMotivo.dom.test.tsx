// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ModalDeMotivo } from './ModalDeMotivo'

function renderModal() {
  const onConfirm = vi.fn()
  const onClose = vi.fn()
  render(<ModalDeMotivo open titulo="Devolver a Borrador" descripcion="Se borrarán los acuerdos." etiqueta="Motivo" obligatorio="Indica el motivo." confirmar="Devolver" onConfirm={onConfirm} onClose={onClose} />)

  return { onConfirm, onClose }
}

describe('ModalDeMotivo', () => {
  it('exige el texto y no confirma en blanco', async () => {
    const usuario = userEvent.setup()
    const { onConfirm } = renderModal()

    await usuario.click(screen.getByRole('button', { name: 'Devolver' }))

    expect(await screen.findByText('Indica el motivo.')).toBeInTheDocument()
    expect(onConfirm).not.toHaveBeenCalled()
  })

  it('confirma con el texto recortado y Cancelar cierra', async () => {
    const usuario = userEvent.setup()
    const { onConfirm, onClose } = renderModal()

    await usuario.type(screen.getByLabelText('Motivo'), '  Cambió el alcance  ')
    await usuario.click(screen.getByRole('button', { name: 'Devolver' }))
    expect(onConfirm).toHaveBeenCalledWith('Cambió el alcance')

    await usuario.click(screen.getByRole('button', { name: 'Cancelar' }))
    expect(onClose).toHaveBeenCalled()
  })
})
