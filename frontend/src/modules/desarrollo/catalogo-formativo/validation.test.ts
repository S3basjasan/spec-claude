import { describe, expect, it } from 'vitest'
import { nombreAreaSchema, sinRepetidos } from './validation'

describe('nombreAreaSchema', () => {
  it('recorta los espacios y colapsa los internos', () => {
    expect(nombreAreaSchema.parse('  Técnica   y datos ')).toBe('Técnica y datos')
  })

  it('rechaza un nombre vacío o de solo espacios', () => {
    expect(nombreAreaSchema.safeParse('   ').success).toBe(false)
    expect(nombreAreaSchema.safeParse('').success).toBe(false)
  })

  it('acepta 60 caracteres y rechaza 61', () => {
    expect(nombreAreaSchema.safeParse('a'.repeat(60)).success).toBe(true)
    expect(nombreAreaSchema.safeParse('a'.repeat(61)).success).toBe(false)
  })
})

describe('sinRepetidos', () => {
  it('detecta ids repetidos', () => {
    expect(sinRepetidos([1, 2, 3])).toBe(true)
    expect(sinRepetidos([1, 2, 1])).toBe(false)
  })
})
