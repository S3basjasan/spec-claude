// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { ESTADOS_PLAN, TIPOS_ACCION } from '../api'
import { BadgeEstadoAccion } from './BadgeEstadoAccion'
import { BadgeEstadoPlan } from './BadgeEstadoPlan'
import { ChipTipo } from './ChipTipo'

describe('badges del plan', () => {
  it('muestra el estado del plan con su texto (Revisión con tilde)', () => {
    const textos = ESTADOS_PLAN.map((estado) => {
      const { unmount, container } = render(<BadgeEstadoPlan estado={estado} />)
      const texto = container.textContent
      unmount()
      return texto
    })

    expect(textos).toEqual(['Borrador', 'Revisión', 'Aprobado', 'Ejecutando', 'Cerrado'])
  })

  it('muestra el estado de la acción, incluida «En curso»', () => {
    render(
      <>
        <BadgeEstadoAccion estado="EnCurso" />
        <BadgeEstadoAccion estado="Cancelada" />
      </>,
    )

    expect(screen.getByText('En curso')).toBeInTheDocument()
    expect(screen.getByText('Cancelada')).toBeInTheDocument()
  })

  it('muestra siempre el nombre del tipo como texto', () => {
    const textos = TIPOS_ACCION.map((tipo) => {
      const { unmount, container } = render(<ChipTipo tipo={tipo} />)
      const texto = container.textContent
      unmount()
      return texto
    })

    expect(textos).toEqual(['Presencial', 'Virtual', 'E-learning', 'OJT', 'Codesarrollo', 'Mentoring'])
    render(<ChipTipo tipo={null} />)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
