import type { ClaveParametro } from './api'
import { parametrosText } from './text'

const ZONA = 'America/Guayaquil'

/** Fecha larga en español, p. ej. «3 de octubre de 2026». Acepta `YYYY-MM-DD` (sin zona) o un instante ISO 8601. */
export function fechaLarga(valor: string): string {
  const soloFecha = /^\d{4}-\d{2}-\d{2}$/.test(valor)
  const fecha = soloFecha ? new Date(`${valor}T12:00:00`) : new Date(valor)
  return new Intl.DateTimeFormat('es-EC', { day: 'numeric', month: 'long', year: 'numeric', timeZone: soloFecha ? undefined : ZONA }).format(fecha)
}

/** Día 1 del mes siguiente (YYYY-MM-DD) según la zona de Compensaciones; solo para la vista previa, el servidor decide la vigencia. */
export function primeroDelMesSiguiente(ahora: Date = new Date()): string {
  const partes = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', timeZone: ZONA }).formatToParts(ahora)
  const anio = Number(partes.find((p) => p.type === 'year')?.value)
  const mes = Number(partes.find((p) => p.type === 'month')?.value)
  const siguiente = mes === 12 ? { anio: anio + 1, mes: 1 } : { anio, mes: mes + 1 }
  return `${siguiente.anio}-${String(siguiente.mes).padStart(2, '0')}-01`
}

/** Valor tal como lo lee una persona: la sensibilidad se muestra como Baja, Normal o Alta, nunca como número. */
export function valorLegible(clave: ClaveParametro, valor: number): string {
  if (clave === 'sensibilidad_radar') {
    const opciones = parametrosText.sensibilidad.options
    return opciones[valor as 1 | 2 | 3]?.label ?? String(valor)
  }
  return String(valor)
}
