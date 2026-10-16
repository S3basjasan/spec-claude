// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ConfirmarMasivaDialog } from './ConfirmarMasivaDialog'

function renderDialogo(props: Partial<Parameters<typeof ConfirmarMasivaDialog>[0]> = {}) {
  const onConfirm = vi.fn()
  const onCancel = vi.fn()
  render(
    <ConfirmarMasivaDialog open cantidad={14} departamento="Tecnología" busy={false} onConfirm={onConfirm} onCancel={onCancel} {...props} />,
  )
  return { onConfirm, onCancel }
}

describe('ConfirmarMasivaDialog', () => {
  it('informa cuántos colaboradores se evaluarán', () => {
    renderDialogo()

    expect(screen.getByRole('alertdialog', { name: 'Generar DNF masiva' })).toHaveAccessibleDescription(
      /Se evaluarán 14 colaboradores del departamento Tecnología/,
    )
  })

  it('usa singular con un solo colaborador', () => {
    renderDialogo({ cantidad: 1 })

    expect(screen.getByRole('alertdialog')).toHaveAccessibleDescription(/Se evaluarán 1 colaborador del departamento/)
  })

  it('Escape y Cancelar cierran sin confirmar', async () => {
    const { onConfirm, onCancel } = renderDialogo()

    await userEvent.keyboard('{Escape}')
    await userEvent.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(onCancel).toHaveBeenCalledTimes(2)
    expect(onConfirm).not.toHaveBeenCalled()
  })

  it('el botón de confirmar es primario porque la acción no destruye nada', async () => {
    const { onConfirm } = renderDialogo()

    const boton = screen.getByRole('button', { name: 'Generar DNF' })
    expect(boton.className).toContain('bg-accent')
    expect(boton.className).not.toContain('bg-danger')
    await userEvent.click(boton)

    expect(onConfirm).toHaveBeenCalledTimes(1)
  })

  it('muestra el error de la confirmación y bloquea los botones mientras trabaja', () => {
    renderDialogo({ busy: true, error: 'No hay colaboradores evaluables.' })

    expect(screen.getByRole('alert')).toHaveTextContent('No hay colaboradores evaluables.')
    expect(screen.getByRole('button', { name: 'Cancelar' })).toBeDisabled()
  })
})
