// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { BadgeDeEstadoDeAccion, BadgeDeEstadoDePdi, BadgeDeResultado, BadgeDeTipoDePdi, BarraDeAvance } from './Etiquetas'

describe('Etiquetas del PDI', () => {
  it('muestran el texto de tipo, estado, acción y resultado (nunca solo color)', () => {
    render(
      <>
        <BadgeDeTipoDePdi valor="Talento" />
        <BadgeDeEstadoDePdi valor="Acordado" />
        <BadgeDeEstadoDeAccion valor="EnCurso" />
        <BadgeDeResultado valor="BrechaCerrada" />
      </>,
    )

    for (const texto of ['Talento', 'Acordado', 'En curso', 'Brecha cerrada']) expect(screen.getByText(texto)).toBeInTheDocument()
  })

  it('la barra de avance expone el porcentaje y «Sin avance» cuando no es calculable', () => {
    const { rerender } = render(<BarraDeAvance valor={40} />)
    expect(screen.getByRole('progressbar', { name: 'Avance' })).toHaveAttribute('aria-valuenow', '40')
    expect(screen.getByText('40 %')).toBeInTheDocument()

    rerender(<BarraDeAvance valor={null} />)
    expect(screen.getByRole('progressbar', { name: 'Avance' })).not.toHaveAttribute('aria-valuenow')
    expect(screen.getByText('Sin avance')).toBeInTheDocument()
  })
})
