// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { resultado, resultadoCumple, resultadoExperiencia, resultadoSinEvaluar } from '../../../../test/adecuacionFixtures'
import { PanelDeDimension } from './PanelDeDimension'

describe('PanelDeDimension', () => {
  it('muestra cada requisito con su estado, su resumen y sus tres barras', () => {
    render(
      <PanelDeDimension
        dimension="Competencia"
        resultados={[resultado(), resultadoCumple(), resultadoSinEvaluar()]}
        cumplimientoActual={1}
        cumplimientoObjetivo={0.83}
        motivoSinFilas={null}
      />,
    )

    const panel = screen.getByRole('region', { name: 'Competencias' })
    expect(within(panel).getByText('actual 100 % · futuro 83 %')).toBeInTheDocument()
    expect(within(panel).getByText('Con brecha')).toBeInTheDocument()
    expect(within(panel).getByText('Cumple')).toBeInTheDocument()
    expect(within(panel).getByText('Sin evaluación')).toBeInTheDocument()
    expect(within(panel).getByText('Nivel actual: C · Requerido: B · Diferencia: −1')).toBeInTheDocument()
    expect(within(panel).getByText('Nivel actual: — · Requerido: C · Diferencia: —')).toBeInTheDocument()
    expect(within(panel).getAllByRole('img')).toHaveLength(3)
    expect(within(panel).getByRole('img', { name: /Comunicación: requisito del puesto actual C, evaluación de la persona C, requisito del puesto futuro B/ })).toBeInTheDocument()
  })

  it('la experiencia se expresa en años', () => {
    render(<PanelDeDimension dimension="Experiencia" resultados={[resultadoExperiencia()]} cumplimientoActual={null} cumplimientoObjetivo={0.6} motivoSinFilas="noEvaluada" />)

    const panel = screen.getByRole('region', { name: 'Experiencia' })
    expect(within(panel).getByText('Experiencia: 3 años · Requerida: 5 años · Diferencia: −2')).toBeInTheDocument()
    expect(within(panel).getByText('actual — · futuro 60 %')).toBeInTheDocument()
  })

  it('explica por qué Conocimientos no tiene filas', () => {
    render(<PanelDeDimension dimension="Conocimiento" resultados={[]} cumplimientoActual={null} cumplimientoObjetivo={null} motivoSinFilas="desactivada" />)

    expect(screen.getByRole('region', { name: 'Conocimientos' })).toHaveTextContent('Estructura Empresarial no asigna grado a los conocimientos')
  })

  it('explica que la experiencia no se evalúa cuando no se indicó el requisito', () => {
    render(<PanelDeDimension dimension="Experiencia" resultados={[]} cumplimientoActual={null} cumplimientoObjetivo={null} motivoSinFilas="noEvaluada" />)

    expect(screen.getByText('No se evalúa porque no se indicó la experiencia requerida.')).toBeInTheDocument()
  })
})
