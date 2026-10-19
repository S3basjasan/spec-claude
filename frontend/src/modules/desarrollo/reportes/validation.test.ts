import { describe, expect, it } from 'vitest'
import { aNumero, calcularRoi, datosDeImpacto, validarEncuesta, validarImpacto, validarSeguimiento } from './validation'

const impactoValido = { indicador: 'Tiempo de cierre', unidad: 'días', valor_antes: '10', valor_despues: '7,5', beneficio_estimado: '5000', justificacion: 'Menos reprocesos' }

describe('validarEncuesta', () => {
  it('exige una calificación entera de 1 a 5', () => {
    expect(validarEncuesta({ calificacion: null, comentario: '' }).calificacion).toBe('Elige una calificación de 1 a 5.')
    expect(validarEncuesta({ calificacion: 0, comentario: '' }).calificacion).toBeDefined()
    expect(validarEncuesta({ calificacion: 6, comentario: '' }).calificacion).toBeDefined()
    expect(validarEncuesta({ calificacion: 2.5, comentario: '' }).calificacion).toBeDefined()
  })

  it('acepta de 1 a 5 y un comentario opcional de hasta 1 000 caracteres', () => {
    for (const n of [1, 2, 3, 4, 5]) expect(validarEncuesta({ calificacion: n, comentario: '' })).toEqual({})
    expect(validarEncuesta({ calificacion: 4, comentario: 'a'.repeat(1000) })).toEqual({})
    expect(validarEncuesta({ calificacion: 4, comentario: 'a'.repeat(1001) }).comentario).toBe('El comentario no puede superar los 1 000 caracteres.')
  })
})

describe('validarSeguimiento', () => {
  it('exige las dos calificaciones', () => {
    const e = validarSeguimiento({ aplicacion: null, apoyo: null, comentario: '' })

    expect(e.aplicacion).toBe('Elige una calificación de aplicación de 1 a 5.')
    expect(e.apoyo).toBe('Elige una calificación de apoyo de 1 a 5.')
  })

  it('acepta valores válidos y limita el comentario', () => {
    expect(validarSeguimiento({ aplicacion: 4, apoyo: 3, comentario: '' })).toEqual({})
    expect(validarSeguimiento({ aplicacion: 4, apoyo: 3, comentario: 'a'.repeat(1001) }).comentario).toBeDefined()
    expect(validarSeguimiento({ aplicacion: 6, apoyo: 3, comentario: '' }).aplicacion).toBeDefined()
  })
})

describe('validarImpacto', () => {
  it('acepta un borrador completo', () => {
    expect(validarImpacto(impactoValido)).toEqual({})
  })

  it('todos los campos son obligatorios', () => {
    const e = validarImpacto({ indicador: ' ', unidad: '', valor_antes: '', valor_despues: '', beneficio_estimado: '', justificacion: '' })

    expect(Object.keys(e).sort()).toEqual(['beneficio_estimado', 'indicador', 'justificacion', 'unidad', 'valor_antes', 'valor_despues'])
  })

  it('rechaza valores no numéricos y un beneficio negativo, y acepta cero', () => {
    expect(validarImpacto({ ...impactoValido, valor_antes: 'abc' }).valor_antes).toBe('Ingresa un número válido.')
    expect(validarImpacto({ ...impactoValido, beneficio_estimado: '-1' }).beneficio_estimado).toBe('El beneficio estimado no puede ser negativo.')
    expect(validarImpacto({ ...impactoValido, beneficio_estimado: '0' })).toEqual({})
  })

  it('respeta las longitudes máximas', () => {
    expect(validarImpacto({ ...impactoValido, indicador: 'a'.repeat(151) }).indicador).toBeDefined()
    expect(validarImpacto({ ...impactoValido, unidad: 'a'.repeat(51) }).unidad).toBeDefined()
    expect(validarImpacto({ ...impactoValido, justificacion: 'a'.repeat(2001) }).justificacion).toBeDefined()
    expect(validarImpacto({ ...impactoValido, indicador: 'a'.repeat(150), unidad: 'a'.repeat(50), justificacion: 'a'.repeat(2000) })).toEqual({})
  })

  it('convierte los números al enviar (coma o punto decimal)', () => {
    const datos = datosDeImpacto(impactoValido)

    expect(datos).toMatchObject({ valor_antes: 10, valor_despues: 7.5, beneficio_estimado: 5000 })
    expect(datosDeImpacto({ ...impactoValido, unidad: '' })).toBeNull()
  })
})

describe('aNumero', () => {
  it('admite coma o punto y devuelve null si está vacío o no es número', () => {
    expect(aNumero('1,5')).toBe(1.5)
    expect(aNumero(' 2.25 ')).toBe(2.25)
    expect(aNumero('')).toBeNull()
    expect(aNumero('x')).toBeNull()
  })
})

describe('calcularRoi', () => {
  it('es (beneficio − costo) / costo × 100', () => {
    expect(calcularRoi(5000, 2000)).toBe(150)
    expect(calcularRoi(500, 2000)).toBe(-75)
  })

  it('no aplica con costo cero, desconocido o beneficio vacío', () => {
    expect(calcularRoi(1000, 0)).toBeNull()
    expect(calcularRoi(1000, null)).toBeNull()
    expect(calcularRoi(null, 2000)).toBeNull()
  })
})
