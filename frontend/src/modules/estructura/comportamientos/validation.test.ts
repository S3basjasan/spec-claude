import { describe, expect, it } from 'vitest'
import { comportamientosSchema } from './validation'

const valido = {
  competencia_id: 3,
  comportamientos: { A: ['a'], B: ['b'], C: ['c'], D: ['d'] },
}

function errores(valores: unknown): Record<string, string> {
  const resultado = comportamientosSchema.safeParse(valores)
  if (resultado.success) return {}
  const campos: Record<string, string> = {}
  for (const issue of resultado.error.issues) {
    campos[issue.path.join('.')] ??= issue.message
  }
  return campos
}

describe('comportamientosSchema', () => {
  it('acepta un registro válido y recorta los textos', () => {
    const resultado = comportamientosSchema.parse({ ...valido, comportamientos: { ...valido.comportamientos, A: ['  Dirige  '] } })

    expect(resultado.comportamientos.A).toEqual(['Dirige'])
  })

  it('exige la competencia al crear', () => {
    expect(errores({ comportamientos: valido.comportamientos })).toEqual({ competencia_id: 'Selecciona una competencia.' })
  })

  it('exige entre 1 y 4 comportamientos por grado', () => {
    expect(errores({ ...valido, comportamientos: { ...valido.comportamientos, A: [] } })).toEqual({
      'comportamientos.A': 'Ingresa al menos un comportamiento.',
    })
    expect(errores({ ...valido, comportamientos: { ...valido.comportamientos, B: ['1', '2', '3', '4', '5'] } })).toEqual({
      'comportamientos.B': 'No puede haber más de 4 comportamientos.',
    })
    expect(errores({ ...valido, comportamientos: { ...valido.comportamientos, C: ['1', '2', '3', '4'] } })).toEqual({})
  })

  it('exige cada texto, de hasta 300 caracteres', () => {
    expect(errores({ ...valido, comportamientos: { ...valido.comportamientos, A: ['   '] } })).toEqual({
      'comportamientos.A.0': 'Ingresa el comportamiento.',
    })
    expect(errores({ ...valido, comportamientos: { ...valido.comportamientos, D: ['x'.repeat(301)] } })).toEqual({
      'comportamientos.D.0': 'El comportamiento no puede superar los 300 caracteres.',
    })
    expect(errores({ ...valido, comportamientos: { ...valido.comportamientos, D: ['x'.repeat(300)] } })).toEqual({})
  })

  it('rechaza un texto repetido dentro del grado sin distinguir mayúsculas, pero lo acepta en otro grado', () => {
    expect(errores({ ...valido, comportamientos: { ...valido.comportamientos, A: ['Dirige', ' dirige '] } })).toEqual({
      'comportamientos.A.1': 'Este comportamiento ya está en el grado.',
    })
    expect(errores({ ...valido, comportamientos: { ...valido.comportamientos, A: ['Igual'], B: ['Igual'] } })).toEqual({})
  })
})
