import type { AvisoCruce } from './api'
import { detectarCrucesLocales } from './fecha'
import { planText } from './text'
import type { AccionAsistente } from './asistente'

const t = planText.asistente.paso3

/**
 * Avisos de cruce por colaborador y acción, listos para mostrar junto al participante. Combina los cruces
 * calculados al instante entre las acciones del propio asistente con los `avisos_cruce` del servidor (que incluyen
 * otros planes) devueltos al guardar el borrador. Solo informan: nunca impiden.
 */
export function avisosPorAccion(acciones: AccionAsistente[], delServidor: AvisoCruce[]): Record<string, Record<number, string[]>> {
  const resultado: Record<string, Record<number, string[]>> = {}
  const claveDe = (id: number) => acciones.find((a) => a.id === id)?.clave
  const poner = (clave: string | undefined, colaboradorId: number, mensaje: string) => {
    if (!clave) return
    const lista = ((resultado[clave] ??= {})[colaboradorId] ??= [])
    if (!lista.includes(mensaje)) lista.push(mensaje)
  }

  const locales = detectarCrucesLocales(
    acciones.map((a, indice) => ({
      id: indice,
      fecha_inicio: a.datos.fecha_inicio,
      fecha_fin: a.datos.fecha_fin,
      colaborador_ids: a.participantes.map((p) => p.id),
    })),
  )
  for (const cruce of locales) {
    poner(acciones[cruce.accion_id].clave, cruce.colaborador_id, t.cruce(acciones[cruce.con_accion_id].datos.nombre))
  }

  const idsDelPlan = new Set(acciones.map((a) => a.id).filter((id): id is number => id !== undefined))
  for (const aviso of delServidor) {
    // Los cruces dentro del propio plan ya los reporta el cálculo local; aquí solo los de otros planes.
    if (idsDelPlan.has(aviso.con_accion_id)) continue
    poner(claveDe(aviso.accion_id), aviso.colaborador_id, t.cruceOtroPlan)
  }

  return resultado
}
