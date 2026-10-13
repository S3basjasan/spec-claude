import { describe, expect, it } from 'vitest'
import { LIMITES, puestoSchema } from './validation'

function valido() {
  return {
    nombre: 'Analista de datos',
    descripcion: 'Convierte datos en reportes.',
    mision: 'Apoyar las decisiones.',
    funciones: [{ tipo: 'Esencial' as const, descripcion: 'Recopilar datos.' }],
    requisitos: [
      { tipo: 'Formacion' as const, descripcion: 'Título afín.' },
      { tipo: 'Experiencia' as const, descripcion: '2 años.' },
    ],
    competencias: [{ competencia_id: 1, grado_requerido: 'B' }],
  }
}

/** Mapa ruta → mensaje de los problemas que encuentra el esquema. */
function errores(valores: unknown): Record<string, string> {
  const resultado = puestoSchema.safeParse(valores)
  if (resultado.success) return {}
  return Object.fromEntries(resultado.error.issues.map((problema) => [problema.path.join('.'), problema.message]))
}

describe('puestoSchema', () => {
  it('acepta un puesto completo y recorta los textos', () => {
    const resultado = puestoSchema.safeParse({ ...valido(), nombre: '  Analista  ' })

    expect(resultado.success).toBe(true)
    expect(resultado.data?.nombre).toBe('Analista')
  })

  it('exige nombre, descripción y misión (también si solo tienen espacios)', () => {
    expect(errores({ ...valido(), nombre: '', descripcion: '   ', mision: '' })).toEqual({
      nombre: 'Ingresa el nombre del puesto.',
      descripcion: 'Ingresa la descripción del puesto.',
      mision: 'Ingresa la misión del puesto.',
    })
  })

  it('respeta los límites de 50, 200 y 200 caracteres', () => {
    expect(errores({ ...valido(), nombre: 'n'.repeat(LIMITES.nombre), descripcion: 'd'.repeat(LIMITES.descripcion), mision: 'm'.repeat(LIMITES.mision) })).toEqual({})
    expect(Object.keys(errores({ ...valido(), nombre: 'n'.repeat(51), descripcion: 'd'.repeat(201), mision: 'm'.repeat(201) }))).toEqual([
      'nombre',
      'descripcion',
      'mision',
    ])
  })

  describe('funciones', () => {
    it('exige al menos una', () => {
      expect(errores({ ...valido(), funciones: [] })).toEqual({ funciones: 'Agrega al menos una función.' })
    })

    it('exige al menos una esencial', () => {
      expect(errores({ ...valido(), funciones: [{ tipo: 'Complementaria', descripcion: 'Solo complementaria.' }] })).toEqual({
        funciones: 'Incluye al menos una función esencial.',
      })
    })

    it('rechaza una descripción repetida sin distinguir mayúsculas ni espacios alrededor', () => {
      const funciones = [
        { tipo: 'Esencial' as const, descripcion: 'Recopilar datos.' },
        { tipo: 'Complementaria' as const, descripcion: '  RECOPILAR datos.  ' },
      ]

      expect(errores({ ...valido(), funciones })).toEqual({ 'funciones.1.descripcion': 'Esta función está repetida.' })
    })

    it('limita la descripción a 500 caracteres y exige texto', () => {
      expect(errores({ ...valido(), funciones: [{ tipo: 'Esencial', descripcion: 'f'.repeat(500) }] })).toEqual({})
      expect(Object.keys(errores({ ...valido(), funciones: [{ tipo: 'Esencial', descripcion: 'f'.repeat(501) }] }))).toEqual([
        'funciones.0.descripcion',
      ])
      expect(errores({ ...valido(), funciones: [{ tipo: 'Esencial', descripcion: ' ' }] })).toEqual({
        'funciones.0.descripcion': 'Ingresa la descripción de la función.',
      })
    })

    it('admite como máximo 20', () => {
      const funcion = (n: number) => ({ tipo: 'Esencial' as const, descripcion: `Función ${n}` })

      expect(errores({ ...valido(), funciones: Array.from({ length: 20 }, (_, n) => funcion(n)) })).toEqual({})
      expect(errores({ ...valido(), funciones: Array.from({ length: 21 }, (_, n) => funcion(n)) })).toEqual({
        funciones: 'Un puesto admite como máximo 20 funciones.',
      })
    })
  })

  describe('requisitos', () => {
    it('exige uno de Formación y uno de Experiencia, y no exige Capacitación', () => {
      expect(errores({ ...valido(), requisitos: [{ tipo: 'Experiencia', descripcion: '2 años.' }] })).toEqual({
        requisitos: 'Incluye al menos un requisito de formación.',
      })
      expect(errores({ ...valido(), requisitos: [{ tipo: 'Formacion', descripcion: 'Título.' }] })).toEqual({
        requisitos: 'Incluye al menos un requisito de experiencia.',
      })
      expect(errores({ ...valido(), requisitos: [] })).toEqual({ requisitos: 'Agrega al menos un requisito.' })
    })

    it('rechaza un texto repetido dentro del mismo tipo pero lo permite en otro tipo', () => {
      const base = [
        { tipo: 'Formacion' as const, descripcion: 'Inglés intermedio.' },
        { tipo: 'Experiencia' as const, descripcion: '2 años.' },
      ]

      expect(errores({ ...valido(), requisitos: [...base, { tipo: 'Formacion', descripcion: ' inglés INTERMEDIO. ' }] })).toEqual({
        'requisitos.2.descripcion': 'Este requisito está repetido en su tipo.',
      })
      expect(errores({ ...valido(), requisitos: [...base, { tipo: 'Capacitacion', descripcion: 'Inglés intermedio.' }] })).toEqual({})
    })

    it('admite como máximo 20', () => {
      const requisitos = [
        { tipo: 'Formacion' as const, descripcion: 'Título.' },
        { tipo: 'Experiencia' as const, descripcion: '2 años.' },
        ...Array.from({ length: 19 }, (_, n) => ({ tipo: 'Capacitacion' as const, descripcion: `Curso ${n}` })),
      ]

      expect(errores({ ...valido(), requisitos })).toEqual({ requisitos: 'Un puesto admite como máximo 20 requisitos.' })
      expect(errores({ ...valido(), requisitos: requisitos.slice(0, 20) })).toEqual({})
    })
  })

  describe('competencias', () => {
    it('exige al menos una', () => {
      expect(errores({ ...valido(), competencias: [] })).toEqual({ competencias: 'Agrega al menos una competencia.' })
    })

    it('exige elegir la competencia y su grado', () => {
      expect(errores({ ...valido(), competencias: [{ competencia_id: undefined, grado_requerido: '' }] })).toEqual({
        'competencias.0.competencia_id': 'Elige una competencia.',
        'competencias.0.grado_requerido': 'Elige el grado requerido.',
      })
    })

    it('rechaza una competencia repetida', () => {
      expect(
        errores({
          ...valido(),
          competencias: [
            { competencia_id: 1, grado_requerido: 'A' },
            { competencia_id: 1, grado_requerido: 'B' },
          ],
        }),
      ).toEqual({ 'competencias.1.competencia_id': 'Esta competencia ya está en el puesto.' })
    })

    it('admite como máximo 20', () => {
      const competencias = Array.from({ length: 21 }, (_, n) => ({ competencia_id: n + 1, grado_requerido: 'A' }))

      expect(errores({ ...valido(), competencias })).toEqual({ competencias: 'Un puesto admite como máximo 20 competencias.' })
      expect(errores({ ...valido(), competencias: competencias.slice(0, 20) })).toEqual({})
    })
  })
})
