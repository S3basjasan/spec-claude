import { describe, expect, it } from 'vitest'
import { detectarCrucesLocales, lunesDeLaSemana, semanasDelMes, sumarDias } from './fecha'

describe('fecha', () => {
  it('suma días cruzando el fin de mes y año', () => {
    expect(sumarDias('2026-01-31', 1)).toBe('2026-02-01')
    expect(sumarDias('2026-12-31', 1)).toBe('2027-01-01')
    expect(sumarDias('2026-03-01', -1)).toBe('2026-02-28')
  })

  it('calcula el lunes de la semana', () => {
    expect(lunesDeLaSemana('2026-10-16')).toBe('2026-10-12')
    expect(lunesDeLaSemana('2026-10-12')).toBe('2026-10-12')
    expect(lunesDeLaSemana('2026-10-18')).toBe('2026-10-12')
  })

  it('arma las semanas del mes de lunes a domingo, marcando los días de otros meses', () => {
    const semanas = semanasDelMes(2026, 9) // octubre 2026 empieza en jueves
    expect(semanas[0][0].iso).toBe('2026-09-28')
    expect(semanas[0][0].delMes).toBe(false)
    expect(semanas[0][3]).toEqual({ iso: '2026-10-01', delMes: true })
    expect(semanas.every((s) => s.length === 7)).toBe(true)
    expect(semanas[semanas.length - 1][6].iso >= '2026-10-31').toBe(true)
  })
})

describe('detectarCrucesLocales', () => {
  const base = [
    { id: 1, fecha_inicio: '2026-03-01', fecha_fin: '2026-03-05', colaborador_ids: [10, 11] },
    { id: 2, fecha_inicio: '2026-03-05', fecha_fin: '2026-03-09', colaborador_ids: [10] },
    { id: 3, fecha_inicio: '2026-04-01', fecha_fin: '2026-04-02', colaborador_ids: [10, 11] },
    { id: 4, fecha_inicio: null, fecha_fin: null, colaborador_ids: [10] },
  ]

  it('detecta el cruce aunque coincida un solo día, para el colaborador común', () => {
    expect(detectarCrucesLocales(base)).toEqual([
      { colaborador_id: 10, accion_id: 1, con_accion_id: 2 },
      { colaborador_id: 10, accion_id: 2, con_accion_id: 1 },
    ])
  })

  it('ignora acciones sin fechas y colaboradores distintos', () => {
    expect(detectarCrucesLocales([base[0], { ...base[1], colaborador_ids: [99] }, base[3]])).toEqual([])
  })
})
