import { describe, expect, it } from 'vitest'
import { AxiosError } from 'axios'
import { httpError, networkError } from '../../../test/apiErrors'
import { categoriaSchema, LIMITE_NOMBRE, problemaDeGuardado } from './validation'

describe('categoriaSchema', () => {
  it('acepta un nombre válido y recorta espacios', () => {
    const resultado = categoriaSchema.safeParse({ nombre: '  Contratos  ' })
    expect(resultado.success).toBe(true)
    expect(resultado.data?.nombre).toBe('Contratos')
  })

  it('exige el nombre tras recortar', () => {
    expect(categoriaSchema.safeParse({ nombre: '' }).success).toBe(false)
    expect(categoriaSchema.safeParse({ nombre: '    ' }).success).toBe(false)
  })

  it('acepta 100 caracteres y rechaza 101', () => {
    expect(categoriaSchema.safeParse({ nombre: 'a'.repeat(LIMITE_NOMBRE) }).success).toBe(true)
    expect(categoriaSchema.safeParse({ nombre: 'a'.repeat(LIMITE_NOMBRE + 1) }).success).toBe(false)
    expect(categoriaSchema.safeParse({ nombre: `  ${'a'.repeat(LIMITE_NOMBRE)}  ` }).success).toBe(true)
  })
})

describe('problemaDeGuardado', () => {
  it('mapea un 422 al campo nombre sin aviso general', () => {
    const error = httpError(422, { message: 'x', code: 'VALIDACION', errors: { nombre: ['Ya existe una categoría con ese nombre.'] } })
    expect(problemaDeGuardado(error)).toEqual({ nombre: 'Ya existe una categoría con ese nombre.', aviso: null })
  })

  it('distingue conflicto, no existe, escritura deshabilitada, red e inesperado', () => {
    expect(problemaDeGuardado(httpError(409, { code: 'CATEGORIA_MODIFICADA' })).aviso).toBe('conflicto')
    expect(problemaDeGuardado(httpError(404, { code: 'CATEGORIA_NO_ENCONTRADA' })).aviso).toBe('noExiste')
    expect(problemaDeGuardado(httpError(503, { code: 'ESCRITURA_DESHABILITADA' })).aviso).toBe('deshabilitada')
    expect(problemaDeGuardado(networkError() as AxiosError).aviso).toBe('red')
    expect(problemaDeGuardado(httpError(500)).aviso).toBe('inesperado')
  })
})
