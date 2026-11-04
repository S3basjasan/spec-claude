import { z } from 'zod'
import { CLAVES, type CambioDeParametro, type ClaveParametro, type Parametro } from './api'
import { parametrosText } from './text'

const text = parametrosText.validation

export type ValoresFormulario = Record<ClaveParametro, string>

/** Un valor escrito: entero estricto dentro del rango de su clave (el servidor aplica la misma regla). */
export function valorSchema(minimo: number, maximo: number) {
  return z
    .string()
    .trim()
    .min(1, text.required)
    .regex(/^\d+$/, text.integer)
    .refine((valor) => Number(valor) >= minimo && Number(valor) <= maximo, text.range(minimo, maximo))
}

/** Esquema del formulario completo: un campo por clave con los rangos que devolvió el servidor. */
export function crearEsquema(parametros: Parametro[]) {
  const forma = Object.fromEntries(parametros.map((p) => [p.clave, valorSchema(p.minimo, p.maximo)])) as Record<ClaveParametro, ReturnType<typeof valorSchema>>
  return z.object(forma)
}

/** Valor con el que el campo arranca: el último guardado (el programado, si lo hay; si no, el vigente). */
export function valorGuardado(parametro: Parametro): number {
  return parametro.valor_programado?.valor ?? parametro.valor
}

export function valoresIniciales(parametros: Parametro[]): ValoresFormulario {
  const valores = Object.fromEntries(CLAVES.map((clave) => [clave, ''])) as ValoresFormulario
  for (const parametro of parametros) {
    valores[parametro.clave] = String(valorGuardado(parametro))
  }
  return valores
}

/** Solo los campos modificados, cada uno con la versión que se leyó. */
export function cambiosDe(parametros: Parametro[], valores: ValoresFormulario): CambioDeParametro[] {
  return parametros
    .filter((p) => valores[p.clave].trim() !== String(valorGuardado(p)))
    .map((p) => ({ clave: p.clave, valor: Number(valores[p.clave].trim()), version: p.version }))
}
