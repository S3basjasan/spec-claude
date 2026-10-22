import type { Preparacion, Resultado } from './api'
import { claveDeResultado, mesesComoNumero } from './validation'

/**
 * Preparación que se muestra mientras el administrador ingresa los tiempos de cierre (solo presentación: el servidor
 * recalcula y guarda la definitiva). Mismas reglas que FR-016: Incompleta si hay requisitos sin evaluación; Lista sin
 * brechas; No lista si alguna supera el umbral; En desarrollo si todas lo cumplen. Devuelve `null` mientras falte
 * algún tiempo y todavía no se pueda clasificar.
 */
export function preparacionDeLaPantalla(
  resultados: readonly Resultado[],
  tiempos: Readonly<Record<string, string>>,
  umbralMeses: number,
): Preparacion | null {
  if (resultados.some((r) => r.estado === 'SinEvaluacion')) return 'Incompleta'

  const brechas = resultados.filter((r) => r.estado === 'ConBrecha')
  if (brechas.length === 0) return 'Lista'

  const meses = brechas.map((b) => mesesComoNumero(tiempos[claveDeResultado(b)] ?? ''))
  if (meses.some((m) => m === null)) return null

  return meses.some((m) => (m as number) > umbralMeses) ? 'NoLista' : 'EnDesarrollo'
}
