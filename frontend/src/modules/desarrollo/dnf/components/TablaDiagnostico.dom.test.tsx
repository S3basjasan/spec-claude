// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { brecha } from '../../../../test/dnfFixtures'
import { TablaDiagnostico } from './TablaDiagnostico'

describe('TablaDiagnostico', () => {
  it('muestra las columnas del diagnóstico', () => {
    render(<TablaDiagnostico brechas={[brecha()]} />)

    for (const columna of ['Competencia', 'Nivel requerido', 'Nivel actual', 'Brecha (grados)', 'Brecha (%)', 'Prioridad', 'Estado']) {
      expect(screen.getByRole('columnheader', { name: columna })).toBeInTheDocument()
    }
  })

  it('ordena de mayor a menor prioridad por defecto', () => {
    render(
      <TablaDiagnostico
        brechas={[
          brecha({ competencia_id: 1, nombre: 'Baja', prioridad: 'Baja', diferencia: -1 }),
          brecha({ competencia_id: 2, nombre: 'Crítica', prioridad: 'Critica', diferencia: -3 }),
          brecha({ competencia_id: 3, nombre: 'Alta', prioridad: 'Alta', diferencia: -2 }),
        ]}
      />,
    )

    const filas = screen.getAllByRole('row').slice(1)
    expect(filas.map((f) => within(f).getAllByRole('cell')[0].textContent)).toEqual([
      expect.stringContaining('Crítica'),
      expect.stringContaining('Alta'),
      expect.stringContaining('Baja'),
    ])
  })

  it('muestra los valores calculados de una brecha', () => {
    render(<TablaDiagnostico brechas={[brecha()]} />)

    const fila = screen.getAllByRole('row')[1]
    expect(within(fila).getByText('Análisis de datos')).toBeInTheDocument()
    expect(within(fila).getByText('-2')).toBeInTheDocument()
    expect(within(fila).getByText('50 %')).toBeInTheDocument()
    expect(within(fila).getByText('Alta')).toBeInTheDocument()
    expect(within(fila).getByText('Con brecha')).toBeInTheDocument()
  })

  it('una competencia sin evaluación no muestra diferencia, brecha ni prioridad', () => {
    render(
      <TablaDiagnostico
        brechas={[brecha({ nivel_actual: null, diferencia: null, brecha_porcentaje: null, prioridad: null, estado: 'SinEvaluacion' })]}
      />,
    )

    const fila = screen.getAllByRole('row')[1]
    expect(within(fila).getByText('Sin evaluación')).toBeInTheDocument()
    expect(within(fila).getAllByText('—').length).toBeGreaterThanOrEqual(3)
    expect(within(fila).getByLabelText('Sin prioridad')).toBeInTheDocument()
  })

  it('marca las competencias inactivas', () => {
    render(<TablaDiagnostico brechas={[brecha({ competencia_activa: false })]} />)

    expect(screen.getByText('Inactiva')).toBeInTheDocument()
  })

  it('sin filas muestra el mensaje indicado', () => {
    render(<TablaDiagnostico brechas={[]} vacio="Nada que mostrar" />)

    expect(screen.getByText('Nada que mostrar')).toBeInTheDocument()
  })
})
