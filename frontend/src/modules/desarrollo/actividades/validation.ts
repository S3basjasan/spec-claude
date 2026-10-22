import { z } from 'zod'
import { MAX_LUGAR, TIPOS_CON_ENLACE, type TipoAccion } from './api'
import { actividadesText } from './text'

const t = actividadesText.formulario.errores

export interface BorradorActividad {
  plan_accion_id: number | null
  lugar_enlace: string
  departamento_id: number | null
}

export type ErroresActividad = Partial<Record<'plan_accion_id' | 'lugar_enlace', string>>

export function esUrlHttp(valor: string): boolean {
  if (/\s/.test(valor)) return false
  try {
    const url = new URL(valor)
    return (url.protocol === 'http:' || url.protocol === 'https:') && url.hostname !== ''
  } catch {
    return false
  }
}

/** Virtual y E-learning exigen una URL http(s); los demás tipos aceptan texto libre. */
export function lugarSchema(tipo: TipoAccion | null) {
  return z
    .string()
    .trim()
    .min(1, t.lugarRequerido)
    .max(MAX_LUGAR, t.lugarLargo)
    .refine((valor) => tipo === null || !TIPOS_CON_ENLACE.includes(tipo) || esUrlHttp(valor), t.urlInvalida)
}

/** Errores por campo del formulario; vacío si los datos son válidos. Al editar la acción es fija y no se valida. */
export function validarActividad(borrador: BorradorActividad, tipo: TipoAccion | null, modo: 'crear' | 'editar'): ErroresActividad {
  const errores: ErroresActividad = {}
  if (modo === 'crear' && borrador.plan_accion_id === null) errores.plan_accion_id = t.accionRequerida
  const lugar = lugarSchema(tipo).safeParse(borrador.lugar_enlace)
  if (!lugar.success) errores.lugar_enlace = lugar.error.issues[0]?.message
  return errores
}

export function tieneErrores(errores: ErroresActividad): boolean {
  return Object.keys(errores).length > 0
}
