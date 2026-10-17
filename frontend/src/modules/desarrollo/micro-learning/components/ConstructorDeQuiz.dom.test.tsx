// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import type { PreguntaBorrador } from '../api'
import { preguntaNueva, type ErroresMicro } from '../validation'
import { ConstructorDeQuiz } from './ConstructorDeQuiz'

function pregunta(enunciado: string, opciones = 2): PreguntaBorrador {
  return { enunciado, opciones: Array.from({ length: opciones }, (_, i) => ({ texto: `Op ${i + 1}`, es_correcta: i === 0 })) }
}

function Envoltorio({ inicial, errores, bloqueado }: { inicial: PreguntaBorrador[]; errores?: ErroresMicro; bloqueado?: boolean }) {
  const [preguntas, setPreguntas] = useState(inicial)
  return (
    <>
      <ConstructorDeQuiz preguntas={preguntas} onChange={setPreguntas} errores={errores} bloqueado={bloqueado} />
      <pre data-testid="estado">{JSON.stringify(preguntas)}</pre>
    </>
  )
}

const estado = () => JSON.parse(screen.getByTestId('estado').textContent ?? '[]') as PreguntaBorrador[]

describe('ConstructorDeQuiz', () => {
  it('sin preguntas muestra el aviso vacío y permite agregar la primera con dos opciones', async () => {
    render(<Envoltorio inicial={[]} />)
    const user = userEvent.setup()

    expect(screen.getByText('Aún no hay preguntas. Agrega la primera.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Agregar pregunta' }))

    expect(estado()).toEqual([preguntaNueva()])
    expect(screen.getByText('Preguntas de opción múltiple (1/20)')).toBeInTheDocument()
  })

  it('no permite pasar de 20 preguntas', () => {
    render(<Envoltorio inicial={Array.from({ length: 20 }, (_, i) => pregunta(`P${i}`))} />)

    expect(screen.getByRole('button', { name: 'Agregar pregunta' })).toBeDisabled()
    expect(screen.getByText('Un quiz admite como máximo 20 preguntas.')).toBeInTheDocument()
  })

  it('agrega hasta 5 opciones y quita hasta quedar con 2', async () => {
    render(<Envoltorio inicial={[pregunta('Uno')]} />)
    const user = userEvent.setup()

    for (let i = 0; i < 3; i++) await user.click(screen.getByRole('button', { name: 'Agregar opción' }))
    expect(estado()[0].opciones).toHaveLength(5)
    expect(screen.getByRole('button', { name: 'Agregar opción' })).toBeDisabled()

    for (let i = 0; i < 3; i++) await user.click(screen.getByRole('button', { name: 'Quitar la opción A de la pregunta 1' }))
    expect(estado()[0].opciones).toHaveLength(2)
    expect(screen.getByRole('button', { name: 'Quitar la opción A de la pregunta 1' })).toBeDisabled()
  })

  it('marca una sola opción correcta por pregunta', async () => {
    render(<Envoltorio inicial={[pregunta('Uno', 3)]} />)

    await userEvent.setup().click(screen.getByRole('radio', { name: 'Marcar la opción C de la pregunta 1 como correcta' }))

    expect(estado()[0].opciones.map((o) => o.es_correcta)).toEqual([false, false, true])
  })

  it('al quitar la opción correcta la primera restante pasa a serlo', async () => {
    render(<Envoltorio inicial={[pregunta('Uno', 3)]} />)

    await userEvent.setup().click(screen.getByRole('button', { name: 'Quitar la opción A de la pregunta 1' }))

    expect(estado()[0].opciones.map((o) => o.es_correcta)).toEqual([true, false])
  })

  it('reordena preguntas con Subir y Bajar, deshabilitados en los extremos, y lo anuncia', async () => {
    render(<Envoltorio inicial={[pregunta('Primera'), pregunta('Segunda')]} />)
    const user = userEvent.setup()

    expect(screen.getByRole('button', { name: 'Subir la pregunta 1' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Bajar la pregunta 2' })).toBeDisabled()
    await user.click(screen.getByRole('button', { name: 'Bajar la pregunta 1' }))

    expect(estado().map((p) => p.enunciado)).toEqual(['Segunda', 'Primera'])
    expect(screen.getByRole('status')).toHaveTextContent('La pregunta 1 ahora es la 2.')
  })

  it('quita una pregunta', async () => {
    render(<Envoltorio inicial={[pregunta('Primera'), pregunta('Segunda')]} />)

    await userEvent.setup().click(screen.getByRole('button', { name: 'Quitar la pregunta 1' }))

    expect(estado().map((p) => p.enunciado)).toEqual(['Segunda'])
  })

  it('muestra los errores en su pregunta', () => {
    render(
      <Envoltorio
        inicial={[pregunta('', 2)]}
        errores={{ 'preguntas.0.enunciado': 'Cada pregunta necesita un enunciado.', 'preguntas.0.correcta': 'Cada pregunta debe tener exactamente una opción correcta.' }}
      />,
    )

    expect(screen.getByText('Cada pregunta necesita un enunciado.')).toBeInTheDocument()
    expect(screen.getByText('Cada pregunta debe tener exactamente una opción correcta.')).toBeInTheDocument()
  })

  it('bloqueado queda en solo lectura con un aviso', () => {
    render(<Envoltorio inicial={[pregunta('Uno')]} bloqueado />)

    expect(screen.getByText(/ya tiene intentos terminados/)).toBeInTheDocument()
    const seccion = screen.getByRole('region', { name: 'Pregunta 1' })
    expect(within(seccion).getByRole('button', { name: 'Quitar la pregunta 1' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Agregar pregunta' })).toBeDisabled()
  })
})
