const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']

/** «2026-07-01» → «1 jul 2026». Devuelve la entrada si no es una fecha válida. */
export function fechaCorta(iso: string | null | undefined): string {
  if (!iso) return '—'
  const [anio, mes, dia] = iso.slice(0, 10).split('-').map(Number)
  if (!anio || !mes || !dia) return iso

  return `${dia} ${MESES[mes - 1]} ${anio}`
}

/** Rango «1 jul 2026 – 31 dic 2026». */
export function rangoDeFechas(desde: string, hasta: string): string {
  return `${fechaCorta(desde)} – ${fechaCorta(hasta)}`
}

/** Fecha y hora «1 jul 2026, 09:00» desde un ISO con hora. */
export function fechaConHora(iso: string | null | undefined): string {
  if (!iso) return '—'
  const hora = iso.length >= 16 ? iso.slice(11, 16) : ''

  return hora === '' ? fechaCorta(iso) : `${fechaCorta(iso)}, ${hora}`
}

/** Días entre dos fechas ISO (hasta − desde). */
export function diasEntre(desde: string, hasta: string): number {
  return Math.round((Date.parse(`${hasta.slice(0, 10)}T00:00:00Z`) - Date.parse(`${desde.slice(0, 10)}T00:00:00Z`)) / 86400000)
}
