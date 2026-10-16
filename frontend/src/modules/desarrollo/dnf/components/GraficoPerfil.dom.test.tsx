// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { brecha } from '../../../../test/dnfFixtures'
import { GraficoPerfil } from './GraficoPerfil'

const brechas = [
  brecha({ competencia_id: 1, nombre: 'Análisis de datos', nivel_requerido: 'A', nivel_actual: 'C' }),
  brecha({ competencia_id: 2, nombre: 'Trabajo en equipo', nivel_requerido: 'B', nivel_actual: 'B', estado: 'Cumple', diferencia: 0, brecha_porcentaje: 0, prioridad: null }),
  brecha({ competencia_id: 3, nombre: 'Liderazgo', nivel_requerido: 'C', nivel_actual: null, estado: 'SinEvaluacion', diferencia: null, brecha_porcentaje: null, prioridad: null }),
]

describe('GraficoPerfil', () => {
  it('se expone como una imagen con un resumen del contenido', () => {
    render(<GraficoPerfil nombreColaborador="Diego Paredes" brechas={brechas} />)

    expect(
      screen.getByRole('img', { name: 'Gráfico del perfil requerido contra el actual de Diego Paredes en 3 competencias.' }),
    ).toBeInTheDocument()
    expect(screen.getByText('Colaborador: Diego Paredes')).toBeInTheDocument()
  })

  it('dibuja una fila por competencia con la marca del requerido y el tramo de brecha solo si hay brecha', () => {
    const { container } = render(<GraficoPerfil nombreColaborador="Diego Paredes" brechas={brechas} />)

    expect(container.querySelectorAll('[data-fila]')).toHaveLength(3)
    expect(container.querySelectorAll('[data-requerido="true"]')).toHaveLength(3)
    expect(container.querySelectorAll('[data-brecha="true"]')).toHaveLength(1)
  })

  it('marca las competencias sin nivel actual con texto', () => {
    render(<GraficoPerfil nombreColaborador="Diego Paredes" brechas={brechas} />)

    expect(screen.getByText('sin nivel actual')).toBeInTheDocument()
  })

  it('incluye la leyenda: perfil actual, perfil ideal y brecha', () => {
    const { container } = render(<GraficoPerfil nombreColaborador="Diego Paredes" brechas={brechas} />)

    expect(container.textContent).toContain('Perfil actual (barras)')
    expect(container.textContent).toContain('Perfil ideal (línea)')
    expect(container.textContent).toContain('Brecha')
  })
})
