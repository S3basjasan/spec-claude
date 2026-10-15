/** Fecha y hora legibles en español a partir de una marca ISO; si no se puede interpretar, devuelve el texto original. */
export function formatearFecha(iso: string): string {
  const fecha = new Date(iso)
  if (Number.isNaN(fecha.getTime())) return iso
  return fecha.toLocaleString('es-EC', { dateStyle: 'medium', timeStyle: 'short' })
}
