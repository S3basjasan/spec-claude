import { describe, expect, it } from 'vitest'
import { conSigno, dolares, fecha, horas, mes, mesLargo, numero, porcentaje, promedio, rangoDelAnio } from './formato'

describe('formato', () => {
  it('numero usa coma decimal y punto de miles', () => {
    expect(numero(1234567.891, 2)).toBe('1.234.567,89')
    expect(numero(12, 0)).toBe('12')
    expect(numero(null)).toBe('—')
  })

  it('dolares, horas, porcentaje y promedio', () => {
    expect(dolares(1250)).toBe('$1.250')
    expect(dolares(310.5, 2)).toBe('$310,50')
    expect(horas(32)).toBe('32 h')
    expect(horas(1.5)).toBe('1,5 h')
    expect(porcentaje(62.5)).toBe('62,5 %')
    expect(promedio(4.25)).toBe('4,3')
    expect(promedio(null)).toBe('—')
    expect(horas(null)).toBe('—')
  })

  it('conSigno muestra el signo explícito', () => {
    expect(conSigno(2.5)).toBe('+2,5')
    expect(conSigno(-1)).toBe('−1,0')
    expect(conSigno(0)).toBe('0,0')
    expect(conSigno(null)).toBe('—')
    expect(conSigno(5, 0)).toBe('+5')
  })

  it('fechas y meses', () => {
    expect(fecha('2026-03-05')).toBe('5 mar 2026')
    expect(fecha('2026-03-05T10:00:00Z')).toBe('5 mar 2026')
    expect(fecha(null)).toBe('—')
    expect(mes('2026-06')).toBe('jun 2026')
    expect(mesLargo('2026-06')).toBe('junio de 2026')
  })

  it('rangoDelAnio abarca del 1 de enero al 31 de diciembre', () => {
    expect(rangoDelAnio(new Date(2026, 9, 17))).toEqual({ desde: '2026-01-01', hasta: '2026-12-31' })
  })
})
