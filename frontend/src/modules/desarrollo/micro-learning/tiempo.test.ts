import { describe, expect, it } from 'vitest'
import { formatearFecha, formatearMinutos, formatearSegundos } from './tiempo'

describe('tiempo', () => {
  it('formatea segundos como m:ss y h:mm:ss', () => {
    expect(formatearSegundos(0)).toBe('0:00')
    expect(formatearSegundos(95)).toBe('1:35')
    expect(formatearSegundos(3725)).toBe('1:02:05')
    expect(formatearSegundos(-4)).toBe('0:00')
  })

  it('formatea la duración en minutos', () => {
    expect(formatearMinutos(12)).toBe('12 min')
    expect(formatearMinutos(60)).toBe('1 h')
    expect(formatearMinutos(65)).toBe('1 h 5 min')
  })

  it('formatea fechas y tolera valores vacíos o inválidos', () => {
    expect(formatearFecha(null)).toBe('')
    expect(formatearFecha('no es fecha')).toBe('')
    expect(formatearFecha('2026-10-16T12:00:00Z')).toMatch(/16\/10\/2026/)
  })
})
