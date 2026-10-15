// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { area } from '../../../../test/catalogoFormativoFixtures'
import { AreaFormativaSelect } from './AreaFormativaSelect'

const areas = [area({ id: 1, nombre: 'Técnica' }), area({ id: 2, nombre: 'Liderazgo' })]

describe('AreaFormativaSelect', () => {
  it('ofrece "Sin área formativa" y las áreas del catálogo', () => {
    render(<AreaFormativaSelect id="a" areas={areas} value={null} onChange={vi.fn()} />)

    const opciones = screen.getAllByRole('option').map((o) => o.textContent)
    expect(opciones).toEqual(['Sin área formativa', 'Técnica', 'Liderazgo'])
  })

  it('elige una sola área y permite quitarla', async () => {
    const onChange = vi.fn()
    render(<AreaFormativaSelect id="a" areas={areas} value={1} onChange={onChange} />)
    const user = userEvent.setup()

    await user.selectOptions(screen.getByLabelText('Área formativa'), '2')
    await user.selectOptions(screen.getByLabelText('Área formativa'), '')

    expect(onChange).toHaveBeenNthCalledWith(1, 2)
    expect(onChange).toHaveBeenNthCalledWith(2, null)
  })

  it('muestra el error del campo', () => {
    render(<AreaFormativaSelect id="a" areas={areas} value={null} onChange={vi.fn()} error="El área formativa no existe." />)

    expect(screen.getByLabelText('Área formativa')).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByText('El área formativa no existe.')).toBeInTheDocument()
  })
})
