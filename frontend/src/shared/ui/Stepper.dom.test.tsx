// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { Stepper } from './Stepper'

describe('Stepper', () => {
  it('marca el paso actual y deshabilita el no alcanzable', () => {
    render(<Stepper label="Pasos" pasos={['Selección', 'Diagnóstico']} actual={0} maximo={0} onSelect={vi.fn()} />)

    expect(screen.getByRole('navigation', { name: 'Pasos' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Selección/ })).toHaveAttribute('aria-current', 'step')
    expect(screen.getByRole('button', { name: /Diagnóstico/ })).toBeDisabled()
  })

  it('permite volver a un paso alcanzado', async () => {
    const onSelect = vi.fn()
    render(<Stepper label="Pasos" pasos={['Selección', 'Diagnóstico']} actual={1} maximo={1} onSelect={onSelect} />)

    await userEvent.click(screen.getByRole('button', { name: /Selección/ }))

    expect(onSelect).toHaveBeenCalledWith(0)
    expect(screen.getByRole('button', { name: /Diagnóstico/ })).toHaveAttribute('aria-current', 'step')
  })
})
