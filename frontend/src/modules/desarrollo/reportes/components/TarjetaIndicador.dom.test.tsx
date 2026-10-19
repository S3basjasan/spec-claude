// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { TarjetaIndicador } from './TarjetaIndicador'

const base = { etiqueta: '% Plan cumplido', valor: '62,5 %', nota: '5 de 8 acciones finalizadas', disponible: true, hayValor: true, anterior: '50,0 %', diferencia: 12.5, variacionPct: 25, unidad: 'pts' }

describe('TarjetaIndicador', () => {
  it('muestra valor, nota y la comparación con el período anterior, con signo y variación', () => {
    render(<TarjetaIndicador {...base} />)

    const tarjeta = screen.getByRole('article', { name: '% Plan cumplido' })
    expect(tarjeta).toHaveTextContent('62,5 %')
    expect(tarjeta).toHaveTextContent('5 de 8 acciones finalizadas')
    expect(tarjeta).toHaveTextContent('Período anterior: 50,0 %')
    expect(tarjeta).toHaveTextContent('+12,5 pts (+25,0 %)')
  })

  it('una variación negativa lleva el signo menos y el color de peligro', () => {
    render(<TarjetaIndicador {...base} diferencia={-3} variacionPct={-6} />)

    const texto = screen.getByText(/−3,0 pts/)
    expect(texto).toHaveTextContent('−3,0 pts (−6,0 %)')
    expect(texto.className).toContain('text-danger')
  })

  it('una variación positiva usa el color de acento y la neutra el atenuado', () => {
    const { rerender } = render(<TarjetaIndicador {...base} />)
    expect(screen.getByText(/\+12,5 pts/).className).toContain('text-accent')

    rerender(<TarjetaIndicador {...base} diferencia={0} variacionPct={0} />)
    expect(screen.getByText(/0,0 pts/).className).toContain('text-muted')
  })

  it('sin datos del período anterior la comparación va sin valor y con una nota', () => {
    render(<TarjetaIndicador {...base} anterior={null} diferencia={null} variacionPct={null} />)

    expect(screen.getByText('Sin datos del período anterior.')).toBeInTheDocument()
    expect(screen.queryByText(/Período anterior:/)).not.toBeInTheDocument()
  })

  it('con anterior igual a cero muestra la diferencia pero no el porcentaje', () => {
    render(<TarjetaIndicador {...base} anterior="0,0 %" diferencia={4} variacionPct={null} />)

    expect(screen.getByText(/\+4,0 pts/)).toHaveTextContent('sin variación porcentual')
  })

  it('un indicador sin datos muestra un guion y la nota que explica por qué', () => {
    render(<TarjetaIndicador {...base} valor="—" hayValor={false} anterior={null} diferencia={null} variacionPct={null} nota="Sin acciones de planes en ejecución o cerrados en el período." />)

    const tarjeta = screen.getByRole('article', { name: '% Plan cumplido' })
    expect(tarjeta).toHaveTextContent('—')
    expect(tarjeta).toHaveTextContent('Sin acciones de planes en ejecución o cerrados en el período.')
  })

  it('un indicador cuya fuente no está disponible lo dice y no muestra comparación', () => {
    render(<TarjetaIndicador {...base} disponible={false} />)

    const tarjeta = screen.getByRole('article', { name: '% Plan cumplido' })
    expect(tarjeta).toHaveTextContent('No disponible')
    expect(tarjeta).not.toHaveTextContent('62,5 %')
    expect(tarjeta).not.toHaveTextContent('Período anterior')
  })
})
