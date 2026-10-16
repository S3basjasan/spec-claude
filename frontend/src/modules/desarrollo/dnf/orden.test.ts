import { describe, expect, it } from 'vitest'
import type { Brecha } from './api'
import { filtrarBrechas, ordenarBrechas, SIN_FILTROS } from './orden'

function brecha(id: number, nombre: string, extra: Partial<Brecha>): Brecha {
  return {
    competencia_id: id,
    codigo: `COM-${id}`,
    nombre,
    tipo: 'Cardinal',
    nivel_requerido: 'A',
    nivel_actual: 'C',
    diferencia: -2,
    brecha_porcentaje: 50,
    prioridad: 'Alta',
    estado: 'ConBrecha',
    competencia_activa: true,
    ...extra,
  }
}

const brechas = [
  brecha(1, 'Zeta', { prioridad: 'Baja', diferencia: -1, tipo: 'Especifica' }),
  brecha(2, 'Beta', { prioridad: 'Alta', diferencia: -2 }),
  brecha(3, 'Alfa', { prioridad: 'Alta', diferencia: -2 }),
  brecha(4, 'Cumple', { prioridad: null, diferencia: 0, estado: 'Cumple', brecha_porcentaje: 0 }),
  brecha(5, 'Crítica', { prioridad: 'Critica', diferencia: -3 }),
  brecha(6, 'Sin nivel', { prioridad: null, diferencia: null, nivel_actual: null, brecha_porcentaje: null, estado: 'SinEvaluacion' }),
  brecha(7, 'Media', { prioridad: 'Media', diferencia: -1 }),
]

describe('ordenarBrechas', () => {
  it('ordena de mayor a menor prioridad y desempata por diferencia y nombre', () => {
    expect(ordenarBrechas(brechas).map((b) => b.nombre)).toEqual(['Crítica', 'Alfa', 'Beta', 'Media', 'Zeta', 'Cumple', 'Sin nivel'])
  })

  it('a igual prioridad pone primero la mayor diferencia en grados', () => {
    const lista = [
      brecha(1, 'Uno', { prioridad: 'Alta', diferencia: -2 }),
      brecha(2, 'Dos', { prioridad: 'Alta', diferencia: -3 }),
    ]

    expect(ordenarBrechas(lista).map((b) => b.nombre)).toEqual(['Dos', 'Uno'])
  })

  it('no modifica la lista original', () => {
    const copia = brechas.map((b) => b.nombre)
    ordenarBrechas(brechas)

    expect(brechas.map((b) => b.nombre)).toEqual(copia)
  })
})

describe('filtrarBrechas', () => {
  it('sin filtros devuelve todo', () => {
    expect(filtrarBrechas(brechas, SIN_FILTROS)).toHaveLength(7)
  })

  it('filtra por prioridad y por estado', () => {
    expect(filtrarBrechas(brechas, { ...SIN_FILTROS, prioridad: 'Alta' })).toHaveLength(2)
    expect(filtrarBrechas(brechas, { ...SIN_FILTROS, estado: 'Cumple' }).map((b) => b.nombre)).toEqual(['Cumple'])
    expect(filtrarBrechas(brechas, { ...SIN_FILTROS, estado: 'SinEvaluacion' })).toHaveLength(1)
  })

  it('«solo con brecha» deja únicamente las competencias con brecha', () => {
    expect(filtrarBrechas(brechas, { ...SIN_FILTROS, soloConBrecha: true })).toHaveLength(5)
  })

  it('combina los filtros', () => {
    expect(filtrarBrechas(brechas, { prioridad: 'Alta', estado: 'Cumple', soloConBrecha: false })).toHaveLength(0)
  })
})
