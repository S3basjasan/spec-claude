// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { hito } from '../../../../test/pdiFixtures'
import { HitosDelPlan } from './HitosDelPlan'

describe('HitosDelPlan', () => {
  it('sin hitos muestra el mensaje vacío', () => {
    render(<HitosDelPlan hitos={[]} puedeRegistrarAvance={false} />)

    expect(screen.getByText('Este plan no tiene hitos.')).toBeInTheDocument()
  })

  it('en solo lectura muestra el estado y las observaciones, y destaca el vencido', () => {
    render(<HitosDelPlan hitos={[hito({ vencido: true, observaciones: 'Reprogramado' })]} puedeRegistrarAvance={false} />)

    expect(screen.getByText('Vencido')).toBeInTheDocument()
    expect(screen.getByText('Reprogramado')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Guardar hito' })).not.toBeInTheDocument()
  })

  it('con permiso de avance guarda estado y observaciones', async () => {
    const usuario = userEvent.setup()
    const onGuardar = vi.fn()
    render(<HitosDelPlan hitos={[hito({ id: 50 })]} puedeRegistrarAvance onGuardar={onGuardar} />)

    await usuario.selectOptions(screen.getByLabelText('Estado del hito'), 'Realizado')
    await usuario.type(screen.getByLabelText('Observaciones'), 'Se revisó con la jefatura')
    await usuario.click(screen.getByRole('button', { name: 'Guardar hito' }))

    expect(onGuardar).toHaveBeenCalledWith(50, 'Realizado', 'Se revisó con la jefatura')
  })
})
