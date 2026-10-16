/** Fecha legible («15 oct 2026») a partir de `AAAA-MM-DD` o de un instante ISO 8601; el texto original si no es válida. */
export function formatearFecha(iso: string): string {
  const soloFecha = /^\d{4}-\d{2}-\d{2}$/.test(iso)
  const fecha = new Date(soloFecha ? `${iso}T00:00:00` : iso)
  if (Number.isNaN(fecha.getTime())) return iso
  return fecha.toLocaleDateString('es-EC', { day: 'numeric', month: 'short', year: 'numeric' })
}
