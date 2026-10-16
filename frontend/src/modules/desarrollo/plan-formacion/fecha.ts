/** Utilidades de fecha de calendario (`AAAA-MM-DD`) sin zonas horarias: se opera siempre en UTC. */

export function aIso(fecha: Date): string {
  return fecha.toISOString().slice(0, 10)
}

export function desdeIso(iso: string): Date {
  const [anio, mes, dia] = iso.split('-').map(Number)
  return new Date(Date.UTC(anio, mes - 1, dia))
}

export function sumarDias(iso: string, dias: number): string {
  const fecha = desdeIso(iso)
  fecha.setUTCDate(fecha.getUTCDate() + dias)
  return aIso(fecha)
}

/** Hoy en la zona del navegador, como `AAAA-MM-DD`. */
export function hoyIso(): string {
  const ahora = new Date()
  return `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}-${String(ahora.getDate()).padStart(2, '0')}`
}

/** Primer día (lunes) de la semana de la fecha. */
export function lunesDeLaSemana(iso: string): string {
  const fecha = desdeIso(iso)
  const dia = (fecha.getUTCDay() + 6) % 7
  return sumarDias(iso, -dia)
}

export interface DiaCalendario {
  iso: string
  delMes: boolean
}

/** Semanas (lunes a domingo) que cubren el mes indicado (`mes` de 0 a 11). */
export function semanasDelMes(anio: number, mes: number): DiaCalendario[][] {
  const primero = aIso(new Date(Date.UTC(anio, mes, 1)))
  const ultimo = aIso(new Date(Date.UTC(anio, mes + 1, 0)))
  const semanas: DiaCalendario[][] = []
  for (let inicio = lunesDeLaSemana(primero); inicio <= ultimo; inicio = sumarDias(inicio, 7)) {
    semanas.push(Array.from({ length: 7 }, (_, i) => {
      const iso = sumarDias(inicio, i)
      return { iso, delMes: desdeIso(iso).getUTCMonth() === mes }
    }))
  }
  return semanas
}

export interface AccionConFechas {
  id: number
  fecha_inicio: string | null
  fecha_fin: string | null
  colaborador_ids: number[]
}

export interface CruceLocal {
  colaborador_id: number
  accion_id: number
  con_accion_id: number
}

/**
 * Cruces entre las acciones del propio asistente (misma regla que el servidor: `inicio_a ≤ fin_b y inicio_b ≤ fin_a`).
 * Las acciones sin fechas se ignoran. Solo informa: nunca impide.
 */
export function detectarCrucesLocales(acciones: AccionConFechas[]): CruceLocal[] {
  const cruces: CruceLocal[] = []
  for (const a of acciones) {
    if (!a.fecha_inicio || !a.fecha_fin) continue
    for (const b of acciones) {
      if (a.id === b.id || !b.fecha_inicio || !b.fecha_fin) continue
      if (a.fecha_inicio > b.fecha_fin || b.fecha_inicio > a.fecha_fin) continue
      for (const colaboradorId of a.colaborador_ids) {
        if (b.colaborador_ids.includes(colaboradorId)) cruces.push({ colaborador_id: colaboradorId, accion_id: a.id, con_accion_id: b.id })
      }
    }
  }
  return cruces
}
