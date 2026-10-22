// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import { Route } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { renderRoutes } from '../../../../test/renderRoutes'
import { rutaResumen } from '../../../../test/rutasFixtures'
import { TarjetaDeRuta } from './TarjetaDeRuta'

function renderTarjeta(sobrescribir = {}) {
  renderRoutes(<Route path="/" element={<TarjetaDeRuta ruta={rutaResumen(sobrescribir)} />} />, '/')
}

describe('TarjetaDeRuta', () => {
  it('muestra nombre, estado, código, departamento, descripción y recuentos', () => {
    renderTarjeta()

    const tarjeta = screen.getByRole('article')
    expect(tarjeta).toHaveTextContent('Carrera de especialista en datos')
    expect(tarjeta).toHaveTextContent('Vigente')
    expect(tarjeta).toHaveTextContent('CAR-0001 · Tecnología')
    expect(tarjeta).toHaveTextContent('Del análisis de datos al liderazgo técnico.')
    expect(tarjeta).toHaveTextContent('3 niveles · 2 participantes')
  })

  it('«Abrir» lleva al editor de la ruta', () => {
    renderTarjeta({ id: 7 })

    expect(screen.getByRole('link', { name: 'Abrir ruta Carrera de especialista en datos' })).toHaveAttribute('href', '/rutas/7')
  })

  it('usa singular con un solo nivel o participante y «Todas las áreas» sin departamento', () => {
    renderTarjeta({ niveles_count: 1, inscritos_count: 1, departamento: null, descripcion: null })

    const tarjeta = screen.getByRole('article')
    expect(tarjeta).toHaveTextContent('1 nivel · 1 participante')
    expect(tarjeta).toHaveTextContent('Todas las áreas')
    expect(tarjeta).toHaveTextContent('Sin descripción.')
  })

  it('el estado se comunica con texto, no solo con color', () => {
    renderTarjeta({ estado: 'Archivada' })

    expect(screen.getByText('Archivada')).toBeInTheDocument()
  })
})
