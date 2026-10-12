import { describe, expect, it } from 'vitest'
import { preguntasSchema } from './validation'

const valido = { competencia_id: 3, preguntas: ['a', 'b', 'c', 'd'] }

function errores(valores: unknown): Record<string, string> {
  const resultado = preguntasSchema.safeParse(valores)
  if (resultado.success) return {}
  const campos: Record<string, string> = {}
  for (const issue of resultado.error.issues) {
    campos[issue.path.join('.')] ??= issue.message
  }
  return campos
}

describe('preguntasSchema', () => {
  it('acepta un registro válido y recorta los textos', () => {
    const resultado = preguntasSchema.parse({ ...valido, preguntas: ['  Uno  ', 'b', 'c', 'd'] })

    expect(resultado.preguntas[0]).toBe('Uno')
  })

  it('exige la competencia al crear', () => {
    expect(errores({ preguntas: valido.preguntas })).toEqual({ competencia_id: 'Selecciona una competencia.' })
  })

  it('exige exactamente cuatro preguntas', () => {
    expect(errores({ ...valido, preguntas: ['a', 'b', 'c'] })).toEqual({ preguntas: 'Ingresa exactamente cuatro preguntas.' })
    expect(errores({ ...valido, preguntas: ['a', 'b', 'c', 'd', 'e'] })).toEqual({ preguntas: 'Ingresa exactamente cuatro preguntas.' })
  })

  it('exige cada texto, de hasta 500 caracteres', () => {
    expect(errores({ ...valido, preguntas: ['   ', 'b', 'c', 'd'] })).toEqual({ 'preguntas.0': 'Ingresa la pregunta.' })
    expect(errores({ ...valido, preguntas: ['a', 'b', 'c', 'x'.repeat(501)] })).toEqual({
      'preguntas.3': 'La pregunta no puede superar los 500 caracteres.',
    })
    expect(errores({ ...valido, preguntas: ['a', 'b', 'c', 'x'.repeat(500)] })).toEqual({})
  })

  it('rechaza una pregunta repetida en el registro sin distinguir mayúsculas ni espacios', () => {
    expect(errores({ ...valido, preguntas: ['Igual', 'b', ' igual ', 'd'] })).toEqual({
      'preguntas.2': 'Esta pregunta ya está en el registro.',
    })
  })
})
