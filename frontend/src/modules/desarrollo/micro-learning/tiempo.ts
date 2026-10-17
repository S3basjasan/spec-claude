/** Formatea segundos como `m:ss` (o `h:mm:ss` si pasa de una hora). */
export function formatearSegundos(total: number): string {
  const segundos = Math.max(0, Math.floor(total))
  const h = Math.floor(segundos / 3600)
  const m = Math.floor((segundos % 3600) / 60)
  const s = segundos % 60
  const ss = String(s).padStart(2, '0')
  return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${ss}` : `${m}:${ss}`
}

/** Duración en minutos como `12 min` o `1 h 5 min`. */
export function formatearMinutos(minutos: number): string {
  if (minutos < 60) return `${minutos} min`
  const resto = minutos % 60
  return resto === 0 ? `${Math.floor(minutos / 60)} h` : `${Math.floor(minutos / 60)} h ${resto} min`
}

/** Fecha ISO como `dd/mm/aaaa`; vacío si no hay fecha. */
export function formatearFecha(iso: string | null | undefined): string {
  if (!iso) return ''
  const fecha = new Date(iso)
  if (Number.isNaN(fecha.getTime())) return ''
  return fecha.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
