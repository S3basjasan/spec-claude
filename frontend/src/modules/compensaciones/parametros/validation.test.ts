import { describe, expect, it } from 'vitest'
import type { ClaveParametro, Parametro } from './api'
import { cambiosDe, crearEsquema, valoresIniciales, valorSchema } from './validation'

const RANGOS: Record<ClaveParametro, [number, number]> = {
  puntos_por_colaborador: [1, 1000],
  plazo_anulacion_dias: [1, 30],
  umbral_concentracion: [1, 100],
  plazo_max_desafio_dias: [1, 90],
  umbral_clima_unidad: [1, 100],
  sensibilidad_radar: [1, 3],
}

function parametro(clave: ClaveParametro, valor: number, extra: Partial<Parametro> = {}): Parametro {
  const [minimo, maximo] = RANGOS[clave]
  return { clave, valor, minimo, maximo, por_defecto: valor, valor_programado: null, version: 0, ultimo_cambio: null, ...extra }
}

const todos = (extra: Partial<Record<ClaveParametro, Partial<Parametro>>> = {}) =>
  (Object.keys(RANGOS) as ClaveParametro[]).map((clave) => parametro(clave, RANGOS[clave][0] + 1, extra[clave]))

describe('valorSchema', () => {
  it.each(Object.entries(RANGOS))('%s acepta sus límites exactos', (_clave, [minimo, maximo]) => {
    const esquema = valorSchema(minimo, maximo)
    expect(esquema.safeParse(String(minimo)).success).toBe(true)
    expect(esquema.safeParse(String(maximo)).success).toBe(true)
  })

  it.each(Object.entries(RANGOS))('%s rechaza lo que está fuera de su rango', (_clave, [minimo, maximo]) => {
    const esquema = valorSchema(minimo, maximo)
    expect(esquema.safeParse(String(minimo - 1)).success).toBe(false)
    expect(esquema.safeParse(String(maximo + 1)).success).toBe(false)
  })

  it.each(['', '  ', '2.5', '-1', '1e3', '1,5', 'diez', '10 días'])('rechaza «%s»', (texto) => {
    expect(valorSchema(1, 1000).safeParse(texto).success).toBe(false)
  })

  it('ignora espacios exteriores', () => {
    expect(valorSchema(1, 30).safeParse(' 7 ').success).toBe(true)
  })
})

describe('crearEsquema', () => {
  it('valida los seis campos con los rangos del servidor', () => {
    const esquema = crearEsquema(todos())
    expect(esquema.safeParse(valoresIniciales(todos())).success).toBe(true)
    expect(esquema.safeParse({ ...valoresIniciales(todos()), plazo_anulacion_dias: '31' }).success).toBe(false)
  })
})

describe('cambiosDe', () => {
  it('devuelve solo los campos modificados, con su versión', () => {
    const lista = todos({ plazo_anulacion_dias: { version: 9 } })
    const valores = { ...valoresIniciales(lista), plazo_anulacion_dias: '12' }

    expect(cambiosDe(lista, valores)).toEqual([{ clave: 'plazo_anulacion_dias', valor: 12, version: 9 }])
  })

  it('toma como valor guardado el programado cuando lo hay', () => {
    const lista = todos({ puntos_por_colaborador: { valor: 100, valor_programado: { valor: 150, vigente_desde: '2026-12-01' } } })

    expect(valoresIniciales(lista).puntos_por_colaborador).toBe('150')
    expect(cambiosDe(lista, valoresIniciales(lista))).toEqual([])
  })
})
