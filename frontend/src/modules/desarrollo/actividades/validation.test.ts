import { describe, expect, it } from 'vitest'
import { esUrlHttp, tieneErrores, validarActividad } from './validation'

const base = { plan_accion_id: 7, lugar_enlace: 'Sala 3', departamento_id: null }

describe('validarActividad', () => {
  it('acepta texto libre en los tipos presenciales', () => {
    for (const tipo of ['Presencial', 'OJT', 'Codesarrollo', 'Mentoring'] as const) {
      expect(validarActividad(base, tipo, 'crear')).toEqual({})
    }
  })

  it('exige una URL http(s) para Virtual y E-learning', () => {
    for (const tipo of ['Virtual', 'ELearning'] as const) {
      expect(validarActividad(base, tipo, 'crear').lugar_enlace).toMatch(/URL válida/)
      expect(validarActividad({ ...base, lugar_enlace: 'https://meet.ejemplo.edu.ec/sala' }, tipo, 'crear')).toEqual({})
    }
  })

  it('exige el lugar y limita su longitud', () => {
    expect(validarActividad({ ...base, lugar_enlace: '   ' }, 'Presencial', 'crear').lugar_enlace).toMatch(/obligatorio/)
    expect(validarActividad({ ...base, lugar_enlace: 'a'.repeat(501) }, 'Presencial', 'crear').lugar_enlace).toMatch(/500/)
    expect(validarActividad({ ...base, lugar_enlace: 'a'.repeat(500) }, 'Presencial', 'crear')).toEqual({})
  })

  it('exige elegir la acción solo al crear', () => {
    expect(validarActividad({ ...base, plan_accion_id: null }, null, 'crear').plan_accion_id).toMatch(/Elige la acción/)
    expect(validarActividad({ ...base, plan_accion_id: null }, 'Presencial', 'editar')).toEqual({})
  })

  it('tieneErrores distingue un formulario válido', () => {
    expect(tieneErrores({})).toBe(false)
    expect(tieneErrores({ lugar_enlace: 'x' })).toBe(true)
  })
})

describe('esUrlHttp', () => {
  it('acepta http y https con host y rechaza el resto', () => {
    expect(esUrlHttp('https://a.com/x?y=1')).toBe(true)
    expect(esUrlHttp('http://a.com')).toBe(true)
    for (const mala of ['sala 3', 'ftp://a.com', 'https://', 'javascript:alert(1)', 'https://a b.com', '']) expect(esUrlHttp(mala)).toBe(false)
  })
})
