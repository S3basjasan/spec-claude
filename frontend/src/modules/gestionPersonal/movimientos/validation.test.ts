import { describe, expect, it } from 'vitest'
import { esFechaValida, esquemaDeAusencia, esquemaDeMovimiento, esquemaDeNovedad, esquemaDeResolucion, esquemaDeRevisionDeAusencia, hoyIso } from './validation'

const novedad = { tipo: 'Permiso' as const, fecha_inicio: '2026-11-02', fecha_fin: '2026-11-03', motivo: 'Trámite' }

describe('validación de movimientos y novedades', () => {
  it('valida el formato y la existencia de las fechas', () => {
    expect(esFechaValida('2026-02-28')).toBe(true)
    expect(esFechaValida('2026-02-30')).toBe(false)
    expect(esFechaValida('02/11/2026')).toBe(false)
    expect(esFechaValida('')).toBe(false)
  })

  it('la novedad exige un período ordenado y la justificación solo si la política la pide', () => {
    expect(esquemaDeNovedad(null).safeParse({ ...novedad, motivo: '' }).success).toBe(true)
    expect(esquemaDeNovedad({ justificacion_obligatoria: true }).safeParse({ ...novedad, motivo: '  ' }).success).toBe(false)
    expect(esquemaDeNovedad(null).safeParse({ ...novedad, fecha_fin: '2026-11-01' }).success).toBe(false)
    expect(esquemaDeNovedad(null).safeParse({ ...novedad, fecha_inicio: 'mañana' }).success).toBe(false)
    expect(esquemaDeNovedad(null).safeParse({ ...novedad, motivo: 'a'.repeat(1001) }).success).toBe(false)
  })

  it('una ausencia no puede ser futura y no usa la anticipación', () => {
    const ayer = '2000-01-01'
    expect(esquemaDeAusencia(null).safeParse({ ...novedad, fecha_inicio: ayer, fecha_fin: ayer }).success).toBe(true)
    expect(esquemaDeAusencia(null).safeParse({ ...novedad, fecha_inicio: hoyIso(), fecha_fin: '2999-01-01' }).success).toBe(false)
    expect(esquemaDeAusencia({ justificacion_obligatoria: true }).safeParse({ ...novedad, fecha_inicio: ayer, fecha_fin: ayer, motivo: '' }).success).toBe(false)
  })

  it('un movimiento exige persona, destino, fecha efectiva y justificación', () => {
    const valido = { colaborador_id: 3, tipo: 'Ascenso' as const, posicion_destino_id: 11, fecha_efectiva: '2026-12-01', motivo: 'Desempeño' }
    expect(esquemaDeMovimiento.safeParse(valido).success).toBe(true)
    expect(esquemaDeMovimiento.safeParse({ ...valido, motivo: '  ' }).success).toBe(false)
    expect(esquemaDeMovimiento.safeParse({ ...valido, colaborador_id: undefined }).success).toBe(false)
    expect(esquemaDeMovimiento.safeParse({ ...valido, posicion_destino_id: undefined }).success).toBe(false)
    expect(esquemaDeMovimiento.safeParse({ ...valido, tipo: 'Permiso' }).success).toBe(false)
  })

  it('la resolución y la revisión de ausencia exigen su razón u observación', () => {
    expect(esquemaDeResolucion.safeParse({ justificacion: ' ' }).success).toBe(false)
    expect(esquemaDeResolucion.safeParse({ justificacion: 'Aprobada' }).success).toBe(true)
    expect(esquemaDeRevisionDeAusencia.safeParse({ resultado: 'Justificada', observacion: '' }).success).toBe(false)
    expect(esquemaDeRevisionDeAusencia.safeParse({ resultado: 'Aprobada', observacion: 'x' }).success).toBe(false)
    expect(esquemaDeRevisionDeAusencia.safeParse({ resultado: 'Revisada', observacion: 'x'.repeat(501) }).success).toBe(false)
  })
})
