// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ConfirmDialog } from './ConfirmDialog'

function renderDialog(props: Partial<Parameters<typeof ConfirmDialog>[0]> = {}) {
  const onConfirm = vi.fn()
  const onCancel = vi.fn()
  render(
    <ConfirmDialog
      open
      title="¿Eliminar la competencia «Liderazgo»?"
      description="Esta acción no se puede deshacer."
      confirmLabel="Eliminar competencia"
      cancelLabel="Cancelar"
      onConfirm={onConfirm}
      onCancel={onCancel}
      {...props}
    />,
  )
  return { onConfirm, onCancel }
}

describe('ConfirmDialog', () => {
  it('es un alertdialog modal con título y descripción accesibles', () => {
    renderDialog()

    const dialogo = screen.getByRole('alertdialog', { name: '¿Eliminar la competencia «Liderazgo»?' })
    expect(dialogo).toHaveAccessibleDescription('Esta acción no se puede deshacer.')
    expect(dialogo).toHaveAttribute('aria-modal', 'true')
  })

  it('no renderiza nada si está cerrado', () => {
    renderDialog({ open: false })

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })

  it('pone el foco en Cancelar al abrir', () => {
    renderDialog()

    expect(screen.getByRole('button', { name: 'Cancelar' })).toHaveFocus()
  })

  it('Cancelar y Escape cancelan sin confirmar', async () => {
    const { onConfirm, onCancel } = renderDialog()
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: 'Cancelar' }))
    await user.keyboard('{Escape}')

    expect(onCancel).toHaveBeenCalledTimes(2)
    expect(onConfirm).not.toHaveBeenCalled()
  })

  it('confirma con el botón de acción', async () => {
    const { onConfirm } = renderDialog()

    await userEvent.setup().click(screen.getByRole('button', { name: 'Eliminar competencia' }))

    expect(onConfirm).toHaveBeenCalledOnce()
  })

  it('atrapa el foco con Tab y Mayús+Tab', async () => {
    renderDialog()
    const user = userEvent.setup()

    await user.tab()
    expect(screen.getByRole('button', { name: 'Eliminar competencia' })).toHaveFocus()
    await user.tab()
    expect(screen.getByRole('button', { name: 'Cancelar' })).toHaveFocus()
    await user.tab({ shift: true })
    expect(screen.getByRole('button', { name: 'Eliminar competencia' })).toHaveFocus()
  })

  it('bloquea los botones y Escape mientras está ocupado', async () => {
    const { onCancel } = renderDialog({ busy: true })

    expect(screen.getByRole('button', { name: 'Cancelar' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Eliminar competencia' })).toBeDisabled()
    await userEvent.setup().keyboard('{Escape}')
    expect(onCancel).not.toHaveBeenCalled()
  })

  it('muestra el error de la última confirmación y sigue abierto', () => {
    renderDialog({ error: 'No se puede eliminar: la competencia está en uso por otro módulo.' })

    expect(screen.getByRole('alert')).toHaveTextContent('está en uso por otro módulo')
    expect(screen.getByRole('alertdialog')).toBeInTheDocument()
  })

  it('devuelve el foco al elemento que lo tenía al cerrarse', async () => {
    function Contenedor({ abierto }: { abierto: boolean }) {
      return (
        <>
          <button>Disparador</button>
          <ConfirmDialog open={abierto} title="t" description="d" confirmLabel="Sí" cancelLabel="No" onConfirm={vi.fn()} onCancel={vi.fn()} />
        </>
      )
    }
    const { rerender } = render(<Contenedor abierto={false} />)
    screen.getByRole('button', { name: 'Disparador' }).focus()

    rerender(<Contenedor abierto />)
    expect(screen.getByRole('button', { name: 'No' })).toHaveFocus()
    rerender(<Contenedor abierto={false} />)

    expect(screen.getByRole('button', { name: 'Disparador' })).toHaveFocus()
  })
})
