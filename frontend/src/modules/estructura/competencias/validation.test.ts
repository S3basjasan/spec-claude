import { describe, expect, it } from 'vitest'
import { competenciaSchema } from './validation'

const valido = {
  nombre: 'Liderazgo',
  descripcion: 'Guiar a otros.',
  tipo: 'Cardinal',
  grados: { A: 'a', B: 'b', C: 'c', D: 'd' },
}

function errores(valores: unknown): Record<string, string> {
  const resultado = competenciaSchema.safeParse(valores)
  if (resultado.success) return {}
  const campos: Record<string, string> = {}
  for (const issue of resultado.error.issues) {
    campos[issue.path.join('.')] ??= issue.message
  }
  return campos
}

describe('competenciaSchema', () => {
  it('acepta una competencia válida y recorta los textos', () => {
    const resultado = competenciaSchema.parse({ ...valido, nombre: '  Liderazgo ', descripcion: ' Guiar a otros. ' })

    expect(resultado.nombre).toBe('Liderazgo')
    expect(resultado.descripcion).toBe('Guiar a otros.')
  })

  it('exige nombre, descripción, tipo y los cuatro grados', () => {
    expect(errores({ nombre: '', descripcion: '', tipo: '', grados: { A: '', B: '', C: '', D: '' } })).toEqual({
      nombre: 'Ingresa el nombre de la competencia.',
      descripcion: 'Ingresa la descripción de la competencia.',
      tipo: 'Selecciona el tipo de competencia.',
      'grados.A': 'Ingresa la descripción del grado A.',
      'grados.B': 'Ingresa la descripción del grado B.',
      'grados.C': 'Ingresa la descripción del grado C.',
      'grados.D': 'Ingresa la descripción del grado D.',
    })
  })

  it('trata un texto de solo espacios como vacío', () => {
    expect(errores({ ...valido, nombre: '   ', grados: { ...valido.grados, C: '  ' } })).toEqual({
      nombre: 'Ingresa el nombre de la competencia.',
      'grados.C': 'Ingresa la descripción del grado C.',
    })
  })

  it('limita el nombre a 50 caracteres', () => {
    expect(errores({ ...valido, nombre: 'a'.repeat(50) })).toEqual({})
    expect(errores({ ...valido, nombre: 'a'.repeat(51) })).toEqual({ nombre: 'El nombre no puede superar los 50 caracteres.' })
  })

  it('limita las descripciones a 500 caracteres', () => {
    expect(errores({ ...valido, descripcion: 'a'.repeat(500), grados: { ...valido.grados, A: 'a'.repeat(500) } })).toEqual({})
    expect(errores({ ...valido, descripcion: 'a'.repeat(501) })).toEqual({
      descripcion: 'La descripción no puede superar los 500 caracteres.',
    })
    expect(errores({ ...valido, grados: { ...valido.grados, D: 'a'.repeat(501) } })).toEqual({
      'grados.D': 'La descripción del grado D no puede superar los 500 caracteres.',
    })
  })

  it('rechaza un tipo desconocido', () => {
    expect(errores({ ...valido, tipo: 'otra' })).toEqual({ tipo: 'Selecciona el tipo de competencia.' })
  })
})
