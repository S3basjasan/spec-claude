import { describe, expect, it } from 'vitest'
import { filtrosHistorialSchema, seleccionSchema } from './validation'

describe('seleccionSchema', () => {
  it('exige colaborador cuando el alcance es Individual', () => {
    const r = seleccionSchema.safeParse({ alcance: 'Individual', colaborador_id: null, departamento_id: null })

    expect(r.success).toBe(false)
    expect(r.error?.issues[0].path).toEqual(['colaborador_id'])
    expect(r.error?.issues[0].message).toBe('Elige un colaborador.')
  })

  it('exige departamento cuando el alcance es Departamento', () => {
    const r = seleccionSchema.safeParse({ alcance: 'Departamento', colaborador_id: 3, departamento_id: null })

    expect(r.success).toBe(false)
    expect(r.error?.issues[0].path).toEqual(['departamento_id'])
  })

  it('acepta una selección completa', () => {
    expect(seleccionSchema.safeParse({ alcance: 'Individual', colaborador_id: 3, departamento_id: null }).success).toBe(true)
    expect(seleccionSchema.safeParse({ alcance: 'Departamento', colaborador_id: null, departamento_id: 2 }).success).toBe(true)
  })

  it('rechaza un alcance desconocido', () => {
    expect(seleccionSchema.safeParse({ alcance: 'Otro', colaborador_id: null, departamento_id: null }).success).toBe(false)
  })
})

describe('filtrosHistorialSchema', () => {
  it('rechaza una fecha inicial posterior a la final', () => {
    const r = filtrosHistorialSchema.safeParse({ fecha_desde: '2026-10-10', fecha_hasta: '2026-10-01' })

    expect(r.success).toBe(false)
    expect(r.error?.issues[0].message).toBe('La fecha inicial no puede ser posterior a la final.')
  })

  it('acepta filtros válidos y vacíos', () => {
    expect(filtrosHistorialSchema.safeParse({}).success).toBe(true)
    expect(filtrosHistorialSchema.safeParse({ fecha_desde: '2026-10-01', prioridad: 'Alta', per_page: 15 }).success).toBe(true)
  })

  it('rechaza un tamaño de página fuera de 1 a 100', () => {
    expect(filtrosHistorialSchema.safeParse({ per_page: 101 }).success).toBe(false)
    expect(filtrosHistorialSchema.safeParse({ per_page: 0 }).success).toBe(false)
  })
})
