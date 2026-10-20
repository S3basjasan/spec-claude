// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { dashboard } from '../../../../test/reportesFixtures'
import { GraficoBarrasHoras } from './GraficoBarrasHoras'
import { GraficoLineaBrechas } from './GraficoLineaBrechas'
import { GraficoPastelTipos } from './GraficoPastelTipos'

const g = dashboard().graficos

describe('GraficoBarrasHoras', () => {
  it('muestra las horas por departamento, incluido «Sin departamento», con una tabla equivalente', () => {
    render(<GraficoBarrasHoras grafico={g.horas_por_departamento} />)

    const grafico = screen.getByRole('img', { name: 'Gráfico de barras con las horas de 2 departamentos' })
    expect(grafico).toHaveTextContent('Tecnología')
    expect(grafico).toHaveTextContent('320 h')
    expect(grafico).toHaveTextContent('Sin departamento')
    const tabla = screen.getByRole('table', { name: 'Horas de formación por departamento', hidden: true })
    expect(within(tabla).getAllByRole('row', { hidden: true })).toHaveLength(3)
  })

  it('sin datos muestra el mensaje y no el gráfico', () => {
    render(<GraficoBarrasHoras grafico={{ disponible: true, datos: [] }} />)

    expect(screen.getByText('Sin horas con estos filtros.')).toBeInTheDocument()
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
  })

  it('con la fuente caída informa que no está disponible', () => {
    render(<GraficoBarrasHoras grafico={{ disponible: false, datos: [] }} />)

    expect(screen.getByText('Este gráfico depende de datos que no están disponibles ahora.')).toBeInTheDocument()
  })
})

describe('GraficoPastelTipos', () => {
  it('lista los siete tipos con horas y porcentaje', () => {
    render(<GraficoPastelTipos grafico={g.distribucion_por_tipo} />)

    expect(screen.getByRole('img', { name: 'Gráfico circular con la distribución de horas por tipo de formación' })).toBeInTheDocument()
    const filas = within(screen.getByRole('table')).getAllByRole('row')
    expect(filas).toHaveLength(8)
    expect(filas[1]).toHaveTextContent('Presencial')
    expect(filas[1]).toHaveTextContent('240 h')
    expect(filas[1]).toHaveTextContent('61,2 %')
    expect(filas[7]).toHaveTextContent('Micro-learning')
    expect(filas[4]).toHaveTextContent('OJT')
  })

  it('sin horas muestra el mensaje vacío', () => {
    render(<GraficoPastelTipos grafico={{ disponible: true, datos: g.distribucion_por_tipo.datos.map((d) => ({ ...d, horas: 0 })) }} />)

    expect(screen.getByText('Sin horas con estos filtros.')).toBeInTheDocument()
  })
})

describe('GraficoLineaBrechas', () => {
  it('dibuja las dos series y ofrece una tabla con cada mes', () => {
    render(<GraficoLineaBrechas grafico={g.evolucion_brechas} />)

    expect(screen.getByRole('img', { name: 'Gráfico de líneas con 2 meses de brechas activas y cerradas' })).toBeInTheDocument()
    const tabla = screen.getByRole('table', { name: 'Evolución de brechas', hidden: true })
    expect(within(tabla).getByRole('row', { name: /enero de 2026/, hidden: true })).toHaveTextContent('14')
    expect(within(tabla).getByRole('row', { name: /febrero de 2026/, hidden: true })).toHaveTextContent('3')
  })

  it('con valores pequeños no repite las marcas del eje (claves únicas)', () => {
    const error = vi.spyOn(console, 'error').mockImplementation(() => {})
    render(<GraficoLineaBrechas grafico={{ disponible: true, datos: [{ mes: '2026-01', activas: 2, cerradas: 1 }, { mes: '2026-02', activas: 1, cerradas: 0 }] }} />)

    expect(error).not.toHaveBeenCalled()
    error.mockRestore()
  })

  it('sin diagnósticos muestra el mensaje vacío', () => {
    render(<GraficoLineaBrechas grafico={{ disponible: true, datos: [{ mes: '2026-01', activas: 0, cerradas: 0 }] }} />)

    expect(screen.getByText('Sin diagnósticos en el período.')).toBeInTheDocument()
  })
})
