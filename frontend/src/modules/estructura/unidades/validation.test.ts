import { describe, expect, it } from 'vitest'
import { MAX_NOMBRE } from './api'
import { datosDeFormulario, esquemaDeUnidad, unidadSchema } from './validation'

const valido = { nombre: 'Reclutamiento', unidad_padre_id: '2', tipo_unidad_id: '4' }

/** Mapa ruta → mensaje de los problemas que encuentra el esquema. */
function errores(valores: unknown): Record<string, string> {
  const resultado = unidadSchema.safeParse(valores)
  if (resultado.success) return {}
  return Object.fromEntries(resultado.error.issues.map((problema) => [problema.path.join('.'), problema.message]))
}

describe('unidadSchema', () => {
  it('acepta una unidad completa y recorta el nombre', () => {
    const resultado = unidadSchema.safeParse({ ...valido, nombre: '  Reclutamiento  ' })

    expect(resultado.success).toBe(true)
    expect(resultado.data?.nombre).toBe('Reclutamiento')
  })

  it('exige el nombre, también si solo tiene espacios', () => {
    expect(errores({ ...valido, nombre: '' })).toEqual({ nombre: 'El nombre es obligatorio.' })
    expect(errores({ ...valido, nombre: '   ' })).toEqual({ nombre: 'El nombre es obligatorio.' })
  })

  it('admite hasta 150 caracteres en el nombre', () => {
    expect(errores({ ...valido, nombre: 'n'.repeat(MAX_NOMBRE) })).toEqual({})
    expect(errores({ ...valido, nombre: 'n'.repeat(MAX_NOMBRE + 1) })).toEqual({
      nombre: 'El nombre no puede superar los 150 caracteres.',
    })
  })

  it('exige elegir el tipo de unidad', () => {
    expect(errores({ ...valido, tipo_unidad_id: '' })).toEqual({ tipo_unidad_id: 'Elige el tipo de unidad.' })
  })

  it('la unidad padre es opcional: vacía significa unidad raíz', () => {
    expect(errores({ ...valido, unidad_padre_id: '' })).toEqual({})
  })

  it('con todos los campos vacíos señala el nombre y el tipo, no la unidad padre', () => {
    expect(Object.keys(errores({ nombre: '', unidad_padre_id: '', tipo_unidad_id: '' })).sort()).toEqual(['nombre', 'tipo_unidad_id'])
  })
})

describe('esquemaDeUnidad', () => {
  it('con otra raíz activa exige elegir la unidad padre, junto con los demás errores', () => {
    const resultado = esquemaDeUnidad(true).safeParse({ nombre: '', unidad_padre_id: '', tipo_unidad_id: '' })

    expect(resultado.success).toBe(false)
    expect(resultado.error?.issues.map((problema) => problema.path.join('.')).sort()).toEqual(['nombre', 'tipo_unidad_id', 'unidad_padre_id'])
  })

  it('con otra raíz activa acepta una unidad padre elegida', () => {
    expect(esquemaDeUnidad(true).safeParse(valido).success).toBe(true)
  })

  it('sin otra raíz activa la unidad padre vacía es válida', () => {
    expect(esquemaDeUnidad(false).safeParse({ ...valido, unidad_padre_id: '' }).success).toBe(true)
  })
})

describe('datosDeFormulario', () => {
  it('convierte los ids en números', () => {
    expect(datosDeFormulario(valido)).toEqual({ nombre: 'Reclutamiento', tipo_unidad_id: 4, unidad_padre_id: 2 })
  })

  it('una unidad padre vacía se envía como null', () => {
    expect(datosDeFormulario({ ...valido, unidad_padre_id: '' })).toEqual({ nombre: 'Reclutamiento', tipo_unidad_id: 4, unidad_padre_id: null })
  })
})
