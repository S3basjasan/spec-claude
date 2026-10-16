// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { BadgeEstado } from './BadgeEstado'
import { BadgePrioridad } from './BadgePrioridad'

describe('BadgePrioridad', () => {
  it.each([
    ['Critica', 'Crítica'],
    ['Alta', 'Alta'],
    ['Media', 'Media'],
    ['Baja', 'Baja'],
  ] as const)('muestra %s con texto', (prioridad, texto) => {
    render(<BadgePrioridad prioridad={prioridad} />)

    expect(screen.getByText(texto)).toBeInTheDocument()
  })

  it('sin prioridad muestra una raya con descripción accesible', () => {
    render(<BadgePrioridad prioridad={null} />)

    expect(screen.getByLabelText('Sin prioridad')).toHaveTextContent('—')
  })
})

describe('BadgeEstado', () => {
  it.each([
    ['ConBrecha', 'Con brecha'],
    ['Cumple', 'Cumple'],
    ['Supera', 'Supera'],
    ['SinEvaluacion', 'Sin evaluación'],
  ] as const)('muestra %s con texto', (estado, texto) => {
    render(<BadgeEstado estado={estado} />)

    expect(screen.getByText(texto)).toBeInTheDocument()
  })
})
