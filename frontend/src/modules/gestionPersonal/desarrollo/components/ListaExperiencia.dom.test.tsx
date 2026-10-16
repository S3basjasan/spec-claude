// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { experiencia } from '../../../../test/desarrolloFixtures'
import { ListaExperiencia } from './ListaExperiencia'

describe('ListaExperiencia', () => {
  it('muestra el estado vacío', () => {
    render(<ListaExperiencia experiencias={[]} editable onAgregar={vi.fn()} onEditar={vi.fn()} />)
    expect(screen.getByText('Experiencia anterior no registrada')).toBeInTheDocument()
  })

  it('una fecha final ausente se muestra «No registrado» y nunca como actual o vigente', () => {
    render(<ListaExperiencia experiencias={[experiencia()]} editable onAgregar={vi.fn()} onEditar={vi.fn()} />)

    expect(screen.getAllByText('No registrado').length).toBeGreaterThanOrEqual(1)
    expect(screen.queryByText(/actual|vigente|presente/i)).not.toBeInTheDocument()
  })

  it('sin permiso de edición no hay botones', () => {
    render(<ListaExperiencia experiencias={[experiencia()]} editable={false} onAgregar={vi.fn()} onEditar={vi.fn()} />)
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
