import { z } from 'zod'
import { MAX_MESES_CIERRE, MAX_OBSERVACIONES, MIN_MESES_CIERRE, type Dimension, type Resultado, type TiempoDeCierre } from './api'
import { adecuacionText } from './text'

const t = adecuacionText

/** Clave estable de un requisito dentro del diagnóstico: `Competencia:12` o `Experiencia:0` (igual que en el servidor). */
export function claveDeResultado(r: { dimension: Dimension; elemento_id: number | null }): string {
  return `${r.dimension}:${r.elemento_id ?? 0}`
}

/** Tiempo de cierre: entero entre 1 y 60 meses. El texto vacío no es un error de formato, solo un tiempo pendiente. */
export const mesesSchema = z.coerce
  .number({ error: t.brechas.errorTiempo })
  .int(t.brechas.errorTiempo)
  .min(MIN_MESES_CIERRE, t.brechas.errorTiempo)
  .max(MAX_MESES_CIERRE, t.brechas.errorTiempo)

export function validarMeses(texto: string): string | undefined {
  if (texto.trim() === '') return undefined
  const resultado = mesesSchema.safeParse(texto.trim())

  return resultado.success ? undefined : (resultado.error.issues[0]?.message ?? t.brechas.errorTiempo)
}

export function mesesComoNumero(texto: string): number | null {
  if (texto.trim() === '' || validarMeses(texto) !== undefined) return null

  return Number(texto.trim())
}

/** Claves de las brechas que todavía no tienen un tiempo válido. */
export function brechasSinTiempo(resultados: readonly Resultado[], tiempos: Readonly<Record<string, string>>): string[] {
  return resultados
    .filter((r) => r.estado === 'ConBrecha')
    .map(claveDeResultado)
    .filter((clave) => mesesComoNumero(tiempos[clave] ?? '') === null)
}

/** Tiempos a enviar al servidor: uno por brecha, solo con valores válidos. */
export function tiemposParaEnviar(resultados: readonly Resultado[], tiempos: Readonly<Record<string, string>>): TiempoDeCierre[] {
  const enviar: TiempoDeCierre[] = []
  for (const r of resultados) {
    if (r.estado !== 'ConBrecha') continue
    const meses = mesesComoNumero(tiempos[claveDeResultado(r)] ?? '')
    if (meses !== null) enviar.push({ dimension: r.dimension, elemento_id: r.elemento_id, meses })
  }

  return enviar
}

export const observacionesSchema = z.string().max(MAX_OBSERVACIONES, `Las observaciones no pueden superar los ${MAX_OBSERVACIONES} caracteres.`)

export function validarObservaciones(texto: string): string | undefined {
  const resultado = observacionesSchema.safeParse(texto)

  return resultado.success ? undefined : resultado.error.issues[0]?.message
}

export const experienciaSchema = z.coerce
  .number({ error: 'La experiencia debe ser un número de años.' })
  .min(0, 'La experiencia no puede ser negativa.')
  .max(60, 'La experiencia no puede superar los 60 años.')
  .refine((n) => Math.round(n * 10) === n * 10 || Math.abs(Math.round(n * 10) - n * 10) < 1e-9, 'La experiencia admite como máximo un decimal.')

export function validarExperiencia(texto: string): string | undefined {
  if (texto.trim() === '') return undefined
  const resultado = experienciaSchema.safeParse(texto.trim().replace(',', '.'))

  return resultado.success ? undefined : resultado.error.issues[0]?.message
}

export function experienciaComoNumero(texto: string): number | undefined {
  if (texto.trim() === '' || validarExperiencia(texto) !== undefined) return undefined

  return Number(texto.trim().replace(',', '.'))
}

export interface BorradorDeParametros {
  competencias: string
  conocimientos: string
  experiencia: string
  umbral: string
}

export type CampoDeParametros = 'pesos' | 'umbral'

const MENSAJE_PESO = 'Los pesos deben ser números enteros entre 0 y 100.'

/** Pesos: tres enteros de 0 a 100 que suman 100. Umbral: entero de al menos 1 mes. */
export function validarParametros(b: BorradorDeParametros): Partial<Record<CampoDeParametros, string>> {
  const errores: Partial<Record<CampoDeParametros, string>> = {}
  const pesos = [b.competencias, b.conocimientos, b.experiencia].map((p) => (p.trim() === '' ? Number.NaN : Number(p)))
  if (pesos.some((p) => !Number.isInteger(p) || p < 0 || p > 100)) errores.pesos = MENSAJE_PESO
  else if (pesos.reduce((a, c) => a + c, 0) !== 100) errores.pesos = 'Los pesos deben sumar 100.'

  const umbral = b.umbral.trim() === '' ? Number.NaN : Number(b.umbral)
  if (!Number.isInteger(umbral) || umbral < 1 || umbral > 120) errores.umbral = 'El umbral debe ser un número entero de meses entre 1 y 120.'

  return errores
}

export function sumaDePesos(b: BorradorDeParametros): number | null {
  const pesos = [b.competencias, b.conocimientos, b.experiencia].map((p) => (p.trim() === '' ? Number.NaN : Number(p)))

  return pesos.some((p) => !Number.isFinite(p)) ? null : pesos.reduce((a, c) => a + c, 0)
}
