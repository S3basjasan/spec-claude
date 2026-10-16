// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { brecha } from '../../../../test/dnfFixtures'
import { TarjetaBrecha } from './TarjetaBrecha'

function renderTarjeta(tieneOferta: boolean, posicion?: number) {
  render(
    <MemoryRouter>
      <TarjetaBrecha brecha={brecha()} tieneOferta={tieneOferta} posicion={posicion} />
    </MemoryRouter>,
  )
}

describe('TarjetaBrecha', () => {
  it('muestra la diferencia con el formato de la especificación y un resumen accesible', () => {
    renderTarjeta(true)

    expect(screen.getByText('Nivel actual: C · Requerido: A · Diferencia: -2 (50 % de brecha)')).toBeInTheDocument()
    expect(
      screen.getByRole('article', { name: 'Análisis de datos, prioridad Alta. Nivel actual C, requerido A, diferencia -2' }),
    ).toBeInTheDocument()
    expect(screen.getByText('Alta')).toBeInTheDocument()
  })

  it('dibuja las barras requerido y actual con su letra visible', () => {
    renderTarjeta(true)

    expect(screen.getByText('Requerido')).toBeInTheDocument()
    expect(screen.getByText('Actual')).toBeInTheDocument()
    expect(screen.getByText('A')).toBeInTheDocument()
    expect(screen.getByText('C')).toBeInTheDocument()
  })

  it('«Ver cursos sugeridos» lleva al catálogo filtrado por la competencia', () => {
    renderTarjeta(true)

    expect(screen.getByRole('link', { name: 'Ver cursos sugeridos para Análisis de datos' })).toHaveAttribute('href', '/formacion?competencia=1')
  })

  it('sin oferta deshabilita el botón y explica el motivo', () => {
    renderTarjeta(false)

    const boton = screen.getByRole('button', { name: 'Ver cursos sugeridos' })
    expect(boton).toBeDisabled()
    expect(boton).toHaveAccessibleDescription('Aún no hay cursos asociados en el catálogo.')
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })

  it('numera la tarjeta cuando es una de las prioritarias', () => {
    renderTarjeta(true, 2)

    expect(screen.getByText('2')).toBeInTheDocument()
  })
})
