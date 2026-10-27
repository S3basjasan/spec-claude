import { offboardingText } from './text'

/** Fecha y hora legibles de un instante ISO; si no se puede interpretar, se muestra tal cual. */
export function momentoLegible(iso: string | null): string {
  if (iso === null) return offboardingText.none
  const valor = new Date(iso)
  return Number.isNaN(valor.getTime()) ? iso : valor.toLocaleString('es-EC', { dateStyle: 'medium', timeStyle: 'short' })
}
