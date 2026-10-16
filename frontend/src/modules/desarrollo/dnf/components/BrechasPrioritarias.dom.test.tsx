// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { brecha } from '../../../../test/dnfFixtures'
import { BrechasPrioritarias } from './BrechasPrioritarias'

const a = brecha({ competencia_id: 1, nombre: 'Crítica uno', prioridad: 'Critica', diferencia: -3 })
const b = brecha({ competencia_id: 2, nombre: 'Alta dos' })
const c = brecha({ competencia_id: 3, nombre: 'Media tres', prioridad: 'Media', diferencia: -1 })
const d = brecha({ competencia_id: 4, nombre: 'Baja cuatro', prioridad: 'Baja', diferencia: -1 })

function renderLista(brechas = [a, b, c, d]) {
  return render(
    <MemoryRouter>
      <BrechasPrioritarias
        prioritarias={brechas.slice(0, 3)}
        brechas={brechas}
        cumplen={[{ competencia_id: 9, nombre: 'Trabajo en equipo', nivel_actual: 'B', estado: 'Cumple' }]}
        tieneOferta={{ '1': true, '2': false, '3': true, '4': true }}
      />
    </MemoryRouter>,
  )
}

describe('BrechasPrioritarias', () => {
  it('muestra «Mis brechas prioritarias» con tres tarjetas numeradas en orden', () => {
    renderLista()

    const seccion = screen.getByRole('region', { name: 'Mis brechas prioritarias' })
    const items = within(seccion).getAllByRole('listitem')
    expect(items).toHaveLength(3)
    expect(items[0]).toHaveTextContent('Crítica uno')
    expect(items[1]).toHaveTextContent('Alta dos')
    expect(items[2]).toHaveTextContent('Media tres')
  })

  it('lista las demás brechas aparte y las competencias que ya cumple en un bloque plegable', () => {
    renderLista()

    const otras = screen.getByRole('region', { name: 'Otras brechas detectadas' })
    expect(within(otras).getByText('Baja cuatro')).toBeInTheDocument()
    expect(screen.getByText('Competencias que ya cumples (1)')).toBeInTheDocument()
    expect(screen.getByText(/Trabajo en equipo/)).toBeInTheDocument()
  })

  it('con menos de cuatro brechas no muestra «Otras brechas detectadas»', () => {
    renderLista([a, b])

    expect(screen.queryByRole('region', { name: 'Otras brechas detectadas' })).not.toBeInTheDocument()
  })
})
