import { z } from 'zod'
import { MODALIDADES, type DatosPosicion, type Modalidad } from './api'
import { posicionesText } from './text'

const text = posicionesText.validation

/** Valor del selector de supervisor para la máxima autoridad (sin supervisor). */
export const SIN_SUPERVISOR = 'ninguno'

/**
 * Replica las reglas de GuardarPosicionRequest del backend. Los selectores trabajan con texto: `''` es "sin elegir".
 * Al editar el puesto no se pide (no cambia). El supervisor es obligatorio; la máxima autoridad se elige expresamente
 * con `SIN_SUPERVISOR`.
 */
export function esquemaDePosicion(exigirPuesto: boolean) {
  return z.object({
    puesto_id: exigirPuesto ? z.string().min(1, text.positionRequired) : z.string(),
    unidad_organizacional_id: z.string().min(1, text.unitRequired),
    posicion_jefe_id: z.string().min(1, text.supervisorRequired),
    modalidad: z
      .string()
      .min(1, text.modalityRequired)
      .refine((valor) => (MODALIDADES as string[]).includes(valor), text.modalityRequired),
  })
}

export type PosicionFormValues = z.infer<ReturnType<typeof esquemaDePosicion>>

/** Valores del formulario → cuerpo de la petición. */
export function datosDeFormulario(valores: PosicionFormValues, incluirPuesto: boolean): DatosPosicion {
  return {
    ...(incluirPuesto ? { puesto_id: Number(valores.puesto_id) } : {}),
    unidad_organizacional_id: Number(valores.unidad_organizacional_id),
    posicion_jefe_id: valores.posicion_jefe_id === SIN_SUPERVISOR ? null : Number(valores.posicion_jefe_id),
    modalidad: valores.modalidad as Modalidad,
  }
}
