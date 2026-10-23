// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { accion, compromiso, pdiAcordado } from '../../../../test/pdiFixtures'
import { ModalDeCierre } from './ModalDeCierre'

const cerrable = () =>
  pdiAcordado({
    compromisos: [
      compromiso({ id: 10, acciones: [accion({ estado: 'Cumplida' })] }),
      compromiso({ id: 11, elemento: { id: 2, nombre: 'Trabajo en equipo' }, acciones: [accion({ id: 101, estado: 'Cancelada' })] }),
    ],
  })

describe('ModalDeCierre', () => {
  it('exige el resultado de cada compromiso antes de confirmar', async () => {
    const usuario = userEvent.setup()
    const onConfirm = vi.fn()
    render(<ModalDeCierre open pdi={cerrable()} onConfirm={onConfirm} onClose={vi.fn()} />)

    await usuario.selectOptions(screen.getByLabelText(/Resultado del compromiso 1/), 'Cerrada')
    await usuario.click(screen.getByRole('button', { name: 'Cerrar plan' }))

    expect(await screen.findByText('Indica el resultado de cada compromiso.')).toBeInTheDocument()
    expect(onConfirm).not.toHaveBeenCalled()
  })

  it('envía los resultados y la conclusión opcional', async () => {
    const usuario = userEvent.setup()
    const onConfirm = vi.fn()
    render(<ModalDeCierre open pdi={cerrable()} onConfirm={onConfirm} onClose={vi.fn()} />)

    await usuario.selectOptions(screen.getByLabelText(/Resultado del compromiso 1/), 'Cerrada')
    await usuario.selectOptions(screen.getByLabelText(/Resultado del compromiso 2/), 'Parcial')
    await usuario.type(screen.getByLabelText('Conclusión (opcional)'), 'Buen avance.')
    await usuario.click(screen.getByRole('button', { name: 'Cerrar plan' }))

    expect(onConfirm).toHaveBeenCalledWith(
      [
        { compromiso_id: 10, resultado: 'Cerrada' },
        { compromiso_id: 11, resultado: 'Parcial' },
      ],
      'Buen avance.',
    )
  })

  it('con acciones abiertas avisa y bloquea el cierre', () => {
    render(<ModalDeCierre open pdi={pdiAcordado()} onConfirm={vi.fn()} onClose={vi.fn()} />)

    expect(screen.getByText('Quedan 1 acciones por cumplir o cancelar antes de cerrar.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Cerrar plan' })).toBeDisabled()
  })
})
