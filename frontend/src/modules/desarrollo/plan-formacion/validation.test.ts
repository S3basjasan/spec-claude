import { describe, expect, it } from 'vitest'
import { ACCION_VACIA, validarAccion, validarPaso1, type ValoresAccion } from './validation'

const accion = (cambios: Partial<ValoresAccion> = {}): ValoresAccion => ({ ...ACCION_VACIA, nombre: 'Taller', competencia_ids: [1], ...cambios })

describe('validarPaso1', () => {
  it('exige nombre y año válidos', () => {
    expect(validarPaso1({ nombre: ' ', anio: '', descripcion: '' })).toMatchObject({ nombre: 'El nombre es obligatorio.', anio: 'El año es obligatorio.' })
    expect(validarPaso1({ nombre: 'Plan', anio: '26', descripcion: '' }).anio).toBe('Ingresa un año válido (por ejemplo, 2026).')
    expect(validarPaso1({ nombre: 'Plan', anio: '2026', descripcion: '' })).toEqual({})
  })

  it('limita nombre y descripción', () => {
    expect(validarPaso1({ nombre: 'x'.repeat(121), anio: '2026', descripcion: '' }).nombre).toMatch(/120/)
    expect(validarPaso1({ nombre: 'Plan', anio: '2026', descripcion: 'x'.repeat(2001) }).descripcion).toMatch(/2000/)
  })
})

describe('validarAccion', () => {
  it('acepta un Borrador con solo nombre y competencias', () => {
    const r = validarAccion(accion(), 2026)
    expect(r).toMatchObject({ ok: true })
    if (r.ok) expect(r.accion).toMatchObject({ tipo: null, fecha_inicio: null, duracion_horas: null, capacidad_maxima: null, costo: null })
  })

  it('exige nombre y al menos una competencia', () => {
    const r = validarAccion(accion({ nombre: '', competencia_ids: [] }), 2026)
    expect(r).toMatchObject({ ok: false, errores: { nombre: 'El nombre de la acción es obligatorio.', competencia_ids: 'Selecciona al menos una competencia objetivo.' } })
  })

  it('valida fechas dentro del año y fin no anterior al inicio', () => {
    expect(validarAccion(accion({ fecha_inicio: '2025-12-30', fecha_fin: '2026-01-02' }), 2026)).toMatchObject({ ok: false, errores: { fecha_inicio: 'Las fechas deben estar dentro de 2026.' } })
    expect(validarAccion(accion({ fecha_inicio: '2026-05-10', fecha_fin: '2026-05-09' }), 2026)).toMatchObject({ ok: false, errores: { fecha_fin: 'La fecha de fin no puede ser anterior a la de inicio.' } })
    expect(validarAccion(accion({ fecha_inicio: '2026-05-10', fecha_fin: '2026-05-10' }), 2026)).toMatchObject({ ok: true })
  })

  it('valida horas, capacidad y costo', () => {
    const r = validarAccion(accion({ duracion_horas: '0', capacidad_maxima: '2.5', costo: '-1' }), 2026)
    expect(r).toMatchObject({ ok: false, errores: { duracion_horas: expect.any(String), capacidad_maxima: expect.any(String), costo: expect.any(String) } })
    const ok = validarAccion(accion({ duracion_horas: '16,5', capacidad_maxima: '20', costo: '0' }), 2026)
    expect(ok).toMatchObject({ ok: true })
    if (ok.ok) expect(ok.accion).toMatchObject({ duracion_horas: 16.5, capacidad_maxima: 20, costo: 0 })
  })
})
