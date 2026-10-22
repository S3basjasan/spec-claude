// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { nivel, nivelesDeEjemplo } from '../../../../test/rutasFixtures'
import { DetalleDeNivel } from './DetalleDeNivel'

function renderDetalle(indice: number) {
  const niveles = nivelesDeEjemplo()
  render(<DetalleDeNivel nivel={niveles[indice]} esPrimero={indice === 0} titulo={`Nivel ${indice + 1}`} subtitulo="subtítulo" />)
}

const tarjeta = (nombre: string) => screen.getByRole('heading', { name: nombre, level: 3 }).closest('section') as HTMLElement

describe('DetalleDeNivel', () => {
  it('muestra tres tarjetas: Competencias, Conocimientos y Experiencia', () => {
    renderDetalle(1)

    expect(screen.getAllByRole('heading', { level: 3 }).map((h) => h.textContent)).toEqual(['Competencias', 'Conocimientos', 'Experiencia'])
  })

  it('solo las competencias llevan grado y diferencial frente al nivel anterior', () => {
    renderDetalle(1)

    const competencias = tarjeta('Competencias')
    expect(within(competencias).getByLabelText('Comunicación: grado B')).toHaveTextContent('B')
    expect(competencias).toHaveTextContent('+1 (antes C)')
    expect(competencias).toHaveTextContent('Nuevo requisito')
  })

  it('el nivel 1 no tiene diferencial y lo dice', () => {
    renderDetalle(0)

    expect(tarjeta('Competencias')).toHaveTextContent('Primer nivel de la ruta')
    expect(tarjeta('Competencias')).not.toHaveTextContent('antes')
  })

  it('Conocimientos es informativo: texto de formación y capacitación, sin grado ni diferencial', () => {
    renderDetalle(2)

    const conocimientos = tarjeta('Conocimientos')
    expect(within(conocimientos).getByText('Informativo')).toBeInTheDocument()
    expect(conocimientos).toHaveTextContent('Ingeniería')
    expect(conocimientos).toHaveTextContent('Excel avanzado')
    expect(conocimientos).toHaveTextContent('SQL')
    expect(conocimientos.querySelector('[aria-label*="grado"]')).toBeNull()
    expect(conocimientos).not.toHaveTextContent('antes')
    expect(conocimientos).not.toHaveTextContent('Nuevo requisito')
  })

  it('Experiencia muestra los años mínimos del nivel, su diferencial y los requisitos del puesto', () => {
    renderDetalle(2)

    const experiencia = tarjeta('Experiencia')
    expect(experiencia).toHaveTextContent('3 años')
    expect(experiencia).toHaveTextContent('+2 años')
    expect(experiencia).toHaveTextContent('3 años') // requisito de texto del puesto
  })

  it('un nivel sin experiencia mínima la muestra como «—» y sin diferencial', () => {
    render(<DetalleDeNivel nivel={nivel({ experiencia: { anios_minimos: null, diferencial_anios: null, requisitos: [] } })} esPrimero={false} titulo="T" subtitulo="s" />)

    expect(tarjeta('Experiencia')).toHaveTextContent('—')
    expect(tarjeta('Experiencia')).toHaveTextContent('Sin dato para comparar')
    expect(tarjeta('Experiencia')).toHaveTextContent('Sin requisitos de experiencia')
  })

  it('lista las competencias que el nivel anterior pedía y este ya no', () => {
    render(
      <DetalleDeNivel
        nivel={nivel({ competencias_quitadas: [{ competencia_id: 9, codigo: 'COM-009', nombre: 'Trabajo en equipo', grado_anterior: 'B' }] })}
        esPrimero={false}
        titulo="T"
        subtitulo="s"
      />,
    )

    expect(tarjeta('Competencias')).toHaveTextContent('Trabajo en equipo (B)')
  })

  it('con Estructura caída (sin competencias ni requisitos) muestra mensajes vacíos y no inventa datos', () => {
    render(
      <DetalleDeNivel
        nivel={nivel({ competencias: [], conocimientos: { informativo: true, formacion: [], capacitacion: [] }, experiencia: { anios_minimos: null, diferencial_anios: null, requisitos: [] } })}
        esPrimero={false}
        titulo="T"
        subtitulo="s"
      />,
    )

    expect(tarjeta('Competencias')).toHaveTextContent('El puesto no define competencias')
    expect(tarjeta('Conocimientos')).toHaveTextContent('no define requisitos de formación ni capacitación')
  })
})
