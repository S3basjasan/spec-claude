// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { area, fila } from '../../../../test/catalogoFormativoFixtures'
import { GrupoPorArea } from './GrupoPorArea'

const filas = [
  fila({ competencia_id: 1, nombre: 'Liderazgo', area_formativa: area({ id: 2, nombre: 'Liderazgo y gestión' }), cursos_count: 1, nivel_actual: 'A' }),
  fila({ competencia_id: 2, nombre: 'Sin clasificar', area_formativa: null, cursos_count: 0 }),
  fila({ competencia_id: 3, nombre: 'Comunicación', area_formativa: area({ id: 1, nombre: 'Habilidades interpersonales' }), cursos_count: 3 }),
]

describe('GrupoPorArea', () => {
  it('agrupa por área en orden alfabético y deja "Sin área formativa" al final', () => {
    render(<GrupoPorArea filas={filas} onAbrir={vi.fn()} />)

    const titulos = screen.getAllByRole('heading', { level: 2 }).map((h) => h.textContent)
    expect(titulos).toEqual(['Habilidades interpersonales', 'Liderazgo y gestión', 'Sin área formativa'])
  })

  it('cada tarjeta muestra tipo, nivel actual y el contador de cursos disponibles', () => {
    render(<GrupoPorArea filas={filas} onAbrir={vi.fn()} />)

    const lider = screen.getByRole('button', { name: /Liderazgo/ })
    expect(within(lider).getByText('Tu nivel: A')).toBeInTheDocument()
    expect(within(lider).getByText('1 curso disponible')).toBeInTheDocument()
    expect(within(lider).getByText('Cardinal')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Comunicación/ })).toHaveTextContent('3 cursos disponibles')
    expect(screen.getByRole('button', { name: /Sin clasificar/ })).toHaveTextContent('Sin nivel registrado')
  })

  it('al hacer clic en una tarjeta pide abrir su detalle', async () => {
    const onAbrir = vi.fn()
    render(<GrupoPorArea filas={filas} onAbrir={onAbrir} />)

    await userEvent.setup().click(screen.getByRole('button', { name: /Comunicación/ }))

    expect(onAbrir).toHaveBeenCalledWith(3)
  })
})
