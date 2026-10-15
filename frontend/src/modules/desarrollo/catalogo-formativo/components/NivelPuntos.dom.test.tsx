// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { NivelPuntos } from './NivelPuntos'

describe('NivelPuntos', () => {
  it('resalta solo el nivel actual y lo dice con texto accesible', () => {
    const { container } = render(<NivelPuntos nivel="B" />)

    expect(screen.getByText('Tu nivel: B')).toBeInTheDocument()
    const resaltados = container.querySelectorAll('[data-resaltado="true"]')
    expect(resaltados).toHaveLength(1)
    expect(resaltados[0]).toHaveTextContent('B')
    expect(container.querySelectorAll('[aria-hidden="true"] span')).toHaveLength(4)
  })

  it('sin nivel registrado deja los cuatro puntos sin resaltar', () => {
    const { container } = render(<NivelPuntos nivel={null} />)

    expect(screen.getByText('Sin nivel registrado')).toBeInTheDocument()
    expect(container.querySelectorAll('[data-resaltado="true"]')).toHaveLength(0)
  })
})
