// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { movilidad } from '../../../../test/desarrolloFixtures'
import { BloqueMovilidad } from './BloqueMovilidad'

describe('BloqueMovilidad', () => {
  it('distingue «No informada» de «No disponible» y muestra la nota', () => {
    const { rerender } = render(<BloqueMovilidad movilidad={movilidad()} editable onActualizar={vi.fn()} />)
    expect(screen.getByText('No informada')).toBeInTheDocument()
    expect(screen.getByText('La persona aún no ha declarado su disponibilidad.')).toBeInTheDocument()
    expect(screen.getByText('Estar disponible no significa que exista un traslado aprobado.')).toBeInTheDocument()

    rerender(<BloqueMovilidad movilidad={movilidad({ estado: 'NoDisponible' })} editable onActualizar={vi.fn()} />)
    expect(screen.getByText('No disponible')).toBeInTheDocument()
  })

  it('muestra fecha de actualización y observaciones', () => {
    render(
      <BloqueMovilidad
        movilidad={movilidad({ estado: 'Disponible', observaciones: 'Otras ciudades', actualizada_at: '2026-06-10T12:00:00Z' })}
        editable
        onActualizar={vi.fn()}
      />,
    )

    expect(screen.getByText('Otras ciudades')).toBeInTheDocument()
    expect(screen.getByText(/Actualizada el/)).toBeInTheDocument()
  })

  it('el botón solo existe si se puede editar y no hay controles de traslado', async () => {
    const onActualizar = vi.fn()
    const { rerender } = render(<BloqueMovilidad movilidad={movilidad()} editable onActualizar={onActualizar} />)
    await userEvent.setup().click(screen.getByRole('button', { name: 'Actualizar disponibilidad' }))
    expect(onActualizar).toHaveBeenCalled()
    expect(screen.queryByText(/traslad(ar|o)\b.*(solicitar|aprobar|asignar)/i)).not.toBeInTheDocument()

    rerender(<BloqueMovilidad movilidad={movilidad()} editable={false} onActualizar={onActualizar} />)
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
