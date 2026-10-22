import { describe, expect, it } from 'vitest'
import type { Resultado } from './api'
import { preparacionDeLaPantalla } from './preparacion'

const base: Resultado = {
  dimension: 'Competencia',
  tipo_elemento: 'Competencia',
  elemento_id: 1,
  nombre: 'Comunicación',
  requerido_actual: null,
  requerido_objetivo: 'B',
  valor: 'C',
  requerido_actual_anios: null,
  requerido_objetivo_anios: null,
  valor_anios: null,
  diferencia: -1,
  estado: 'ConBrecha',
  estado_etiqueta: 'Con brecha',
  tiempo_cierre_meses: null,
}
const cumple: Resultado = { ...base, elemento_id: 2, estado: 'Cumple', diferencia: 0 }
const sinValor: Resultado = { ...base, elemento_id: 3, estado: 'SinEvaluacion', diferencia: null, valor: null }

describe('preparación en pantalla', () => {
  it('es Lista sin brechas', () => {
    expect(preparacionDeLaPantalla([cumple], {}, 12)).toBe('Lista')
  })

  it('es Incompleta si algún requisito no se evaluó, aunque falten tiempos', () => {
    expect(preparacionDeLaPantalla([base, sinValor], {}, 12)).toBe('Incompleta')
  })

  it('no clasifica mientras falte el tiempo de alguna brecha', () => {
    expect(preparacionDeLaPantalla([base, cumple], {}, 12)).toBeNull()
  })

  it('es En desarrollo con tiempos dentro del umbral (incluido el límite) y No lista si lo superan', () => {
    expect(preparacionDeLaPantalla([base], { 'Competencia:1': '12' }, 12)).toBe('EnDesarrollo')
    expect(preparacionDeLaPantalla([base], { 'Competencia:1': '13' }, 12)).toBe('NoLista')
  })
})
