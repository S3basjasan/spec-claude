import { describe, expect, it } from 'vitest'
import { cambiosDe, datosDeFormulario, fichaSchema, hoyIso, valoresVacios, type FichaFormValues } from './validation'

function valido(cambios: Partial<FichaFormValues> = {}): FichaFormValues {
  return { ...valoresVacios(), numero_identificacion: '0102030405', nombres: 'Ana', apellidos: 'Núñez', ...cambios }
}

/** Mapa campo → mensaje de los problemas que encuentra el esquema. */
function errores(valores: FichaFormValues): Record<string, string> {
  const resultado = fichaSchema.safeParse(valores)
  if (resultado.success) return {}
  return Object.fromEntries(resultado.error.issues.map((problema) => [problema.path.join('.'), problema.message]))
}

describe('fichaSchema', () => {
  it('acepta una ficha mínima', () => {
    expect(errores(valido())).toEqual({})
  })

  it('exige identificación, nombres y apellidos tras recortar espacios', () => {
    const resultado = errores(valido({ numero_identificacion: '   ', nombres: ' ', apellidos: '' }))

    expect(Object.keys(resultado)).toEqual(expect.arrayContaining(['numero_identificacion', 'nombres', 'apellidos']))
  })

  it('aplica las longitudes máximas', () => {
    const resultado = errores(valido({ numero_identificacion: '9'.repeat(51), nombres: 'a'.repeat(151), telefono: '1'.repeat(31) }))

    expect(resultado.numero_identificacion).toBe('No puede superar los 50 caracteres.')
    expect(resultado.nombres).toBeDefined()
    expect(resultado.telefono).toBeDefined()
  })

  it('rechaza una fecha de nacimiento futura y acepta la de hoy', () => {
    expect(errores(valido({ fecha_nacimiento: '2999-01-01' })).fecha_nacimiento).toBe('La fecha de nacimiento no puede ser futura.')
    expect(errores(valido({ fecha_nacimiento: hoyIso() }))).toEqual({})
  })

  it('valida hijos, correo y teléfono', () => {
    const resultado = errores(valido({ numero_hijos: '-1', correo_contacto: 'no-es-correo', telefono: 'abc' }))

    expect(resultado.numero_hijos).toBeDefined()
    expect(resultado.correo_contacto).toBeDefined()
    expect(resultado.telefono).toBeDefined()
    expect(errores(valido({ numero_hijos: '0', telefono: '+593 (2) 555-0101' }))).toEqual({})
  })

  it('exige nombre y teléfono del contacto de emergencia juntos, o los tres vacíos', () => {
    expect(Object.keys(errores(valido({ emergencia_relacion: 'Madre' })))).toEqual(['emergencia_nombre', 'emergencia_telefono'])
    expect(errores(valido({ emergencia_nombre: 'Rosa', emergencia_telefono: '0999999999' }))).toEqual({})
  })
})

describe('datosDeFormulario y cambiosDe', () => {
  it('convierte vacíos a null y los hijos a número, conservando 0', () => {
    const datos = datosDeFormulario(valido({ numero_hijos: '0', genero: '  ' }))

    expect(datos.numero_hijos).toBe(0)
    expect(datos.genero).toBeNull()
    expect(datos.fecha_nacimiento).toBeNull()
  })

  it('calcula solo los campos que cambian', () => {
    const original = datosDeFormulario(valido())
    const nuevo = datosDeFormulario(valido({ nombres: 'Ana María', nacionalidad: 'Ecuatoriana' }))

    expect(cambiosDe(original, nuevo)).toEqual({ nombres: 'Ana María', nacionalidad: 'Ecuatoriana' })
    expect(cambiosDe(original, original)).toEqual({})
  })
})
