import { describe, expect, it } from 'vitest'
import type { Resultado } from './api'
import {
  brechasSinTiempo,
  claveDeResultado,
  experienciaComoNumero,
  mesesComoNumero,
  tiemposParaEnviar,
  validarExperiencia,
  validarMeses,
  sumaDePesos,
  validarObservaciones,
  validarParametros,
} from './validation'

function resultado(sobre: Partial<Resultado>): Resultado {
  return {
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
    ...sobre,
  }
}

describe('tiempo de cierre', () => {
  it('acepta enteros entre 1 y 60', () => {
    expect(validarMeses('1')).toBeUndefined()
    expect(validarMeses('60')).toBeUndefined()
    expect(mesesComoNumero(' 12 ')).toBe(12)
  })

  it('rechaza 0, más de 60, decimales y texto', () => {
    for (const texto of ['0', '61', '-3', '2.5', 'seis']) {
      expect(validarMeses(texto)).toBeDefined()
      expect(mesesComoNumero(texto)).toBeNull()
    }
  })

  it('el texto vacío es un tiempo pendiente, no un error de formato', () => {
    expect(validarMeses('')).toBeUndefined()
    expect(mesesComoNumero('')).toBeNull()
  })
})

describe('brechas y tiempos', () => {
  const resultados = [
    resultado({ elemento_id: 1 }),
    resultado({ elemento_id: 2, estado: 'Cumple', diferencia: 0 }),
    resultado({ dimension: 'Experiencia', tipo_elemento: null, elemento_id: null, nombre: null, diferencia: -2 }),
  ]

  it('identifica las brechas sin tiempo válido', () => {
    expect(brechasSinTiempo(resultados, {})).toEqual(['Competencia:1', 'Experiencia:0'])
    expect(brechasSinTiempo(resultados, { 'Competencia:1': '6', 'Experiencia:0': '99' })).toEqual(['Experiencia:0'])
    expect(brechasSinTiempo(resultados, { 'Competencia:1': '6', 'Experiencia:0': '24' })).toEqual([])
  })

  it('arma los tiempos a enviar solo para las brechas', () => {
    expect(tiemposParaEnviar(resultados, { 'Competencia:1': '6', 'Competencia:2': '3', 'Experiencia:0': '24' })).toEqual([
      { dimension: 'Competencia', elemento_id: 1, meses: 6 },
      { dimension: 'Experiencia', elemento_id: null, meses: 24 },
    ])
  })

  it('la clave de un requisito de experiencia termina en 0', () => {
    expect(claveDeResultado({ dimension: 'Experiencia', elemento_id: null })).toBe('Experiencia:0')
  })
})

describe('observaciones y experiencia', () => {
  it('limita las observaciones a 500 caracteres', () => {
    expect(validarObservaciones('a'.repeat(500))).toBeUndefined()
    expect(validarObservaciones('a'.repeat(501))).toBeDefined()
  })

  it('valida la experiencia requerida: 0 a 60 años con un decimal', () => {
    expect(validarExperiencia('')).toBeUndefined()
    expect(validarExperiencia('5')).toBeUndefined()
    expect(validarExperiencia('2,5')).toBeUndefined()
    expect(validarExperiencia('-1')).toBeDefined()
    expect(validarExperiencia('61')).toBeDefined()
    expect(validarExperiencia('1.55')).toBeDefined()
    expect(experienciaComoNumero('2,5')).toBe(2.5)
    expect(experienciaComoNumero('')).toBeUndefined()
  })
})

describe('parámetros', () => {
  const base = { competencias: '40', conocimientos: '30', experiencia: '30', umbral: '12' }

  it('acepta pesos que suman 100 y un umbral entero', () => {
    expect(validarParametros(base)).toEqual({})
    expect(sumaDePesos(base)).toBe(100)
  })

  it('rechaza pesos que no suman 100, negativos o no enteros', () => {
    expect(validarParametros({ ...base, experiencia: '20' }).pesos).toBe('Los pesos deben sumar 100.')
    expect(validarParametros({ ...base, competencias: '-10', conocimientos: '60', experiencia: '50' }).pesos).toContain('enteros')
    expect(validarParametros({ ...base, competencias: '40.5', conocimientos: '29.5' }).pesos).toContain('enteros')
    expect(validarParametros({ ...base, competencias: '' }).pesos).toContain('enteros')
    expect(sumaDePesos({ ...base, competencias: '' })).toBeNull()
  })

  it('rechaza un umbral menor que 1, mayor que 120 o no entero', () => {
    for (const umbral of ['0', '121', '2.5', 'abc', '']) expect(validarParametros({ ...base, umbral }).umbral).toBeDefined()
    expect(validarParametros({ ...base, umbral: '1' }).umbral).toBeUndefined()
  })
})
