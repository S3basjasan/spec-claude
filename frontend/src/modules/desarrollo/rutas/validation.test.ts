import { describe, expect, it } from 'vitest'
import { aNumero, tieneErrores, validarMotivo, validarNivel, validarRuta } from './validation'

describe('validarRuta', () => {
  const valida = { nombre: 'Ruta de datos', tipo: 'Especialista', descripcion: '', departamento_id: 1 } as const

  it('acepta una ruta válida', () => {
    expect(validarRuta(valida)).toEqual({})
  })

  it('exige nombre, con un máximo de 80 caracteres', () => {
    expect(validarRuta({ ...valida, nombre: '   ' }).nombre).toBeDefined()
    expect(validarRuta({ ...valida, nombre: 'a'.repeat(81) }).nombre).toMatch(/80/)
    expect(validarRuta({ ...valida, nombre: 'a'.repeat(80) }).nombre).toBeUndefined()
  })

  it('exige el tipo', () => {
    expect(validarRuta({ ...valida, tipo: '' }).tipo).toBeDefined()
  })

  it('limita la descripción a 500 caracteres', () => {
    expect(validarRuta({ ...valida, descripcion: 'd'.repeat(501) }).descripcion).toMatch(/500/)
    expect(validarRuta({ ...valida, descripcion: 'd'.repeat(500) }).descripcion).toBeUndefined()
  })

  it('no admite departamento en una ruta Interáreas', () => {
    expect(validarRuta({ ...valida, tipo: 'Interareas', departamento_id: 1 }).departamento_id).toBeDefined()
    expect(validarRuta({ ...valida, tipo: 'Interareas', departamento_id: null })).toEqual({})
  })
})

describe('validarNivel', () => {
  it('acepta nombre y experiencia vacía o entre 0 y 60', () => {
    expect(validarNivel({ nombre: 'Analista', experiencia: '' })).toEqual({})
    expect(validarNivel({ nombre: 'Analista', experiencia: '0' })).toEqual({})
    expect(validarNivel({ nombre: 'Analista', experiencia: '2,5' })).toEqual({})
    expect(validarNivel({ nombre: 'Analista', experiencia: '60' })).toEqual({})
  })

  it('rechaza experiencia fuera de rango o que no es número', () => {
    expect(validarNivel({ nombre: 'Analista', experiencia: '-1' }).experiencia).toBeDefined()
    expect(validarNivel({ nombre: 'Analista', experiencia: '61' }).experiencia).toBeDefined()
    expect(validarNivel({ nombre: 'Analista', experiencia: 'abc' }).experiencia).toBeDefined()
  })

  it('exige un nombre de hasta 80 caracteres', () => {
    expect(validarNivel({ nombre: '', experiencia: '' }).nombre).toBeDefined()
    expect(validarNivel({ nombre: 'n'.repeat(81), experiencia: '' }).nombre).toMatch(/80/)
  })
})

describe('validarMotivo', () => {
  it('exige un motivo de hasta 500 caracteres', () => {
    expect(validarMotivo('')).toBeDefined()
    expect(validarMotivo('   ')).toBeDefined()
    expect(validarMotivo('m'.repeat(501))).toMatch(/500/)
    expect(validarMotivo('Cambió de área')).toBeUndefined()
  })
})

describe('utilidades', () => {
  it('aNumero admite la coma decimal y distingue vacío de inválido', () => {
    expect(aNumero('')).toBeNull()
    expect(aNumero('1,5')).toBe(1.5)
    expect(Number.isNaN(aNumero('x'))).toBe(true)
  })

  it('tieneErrores ignora los campos indefinidos', () => {
    expect(tieneErrores({})).toBe(false)
    expect(tieneErrores({ nombre: undefined })).toBe(false)
    expect(tieneErrores({ nombre: 'Falta' })).toBe(true)
  })
})
