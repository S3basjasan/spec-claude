import { z } from 'zod'
import type { ParametrosConservados } from './api'
import { movimientosText } from './text'

const text = movimientosText.validation
const FECHA = /^\d{4}-\d{2}-\d{2}$/

export const MOTIVO_MAXIMO = 1000
export const OBSERVACION_MAXIMA = 500

/** Clave de operación (`Idempotency-Key`): identifica un formulario abierto, de modo que reenviarlo no duplica. */
export const nuevaClave = (): string => crypto.randomUUID()

/** Fecha de hoy (AAAA-MM-DD) en la zona local del navegador; el servidor decide con la zona de la organización. */
export function hoyIso(): string {
  const ahora = new Date()
  const mes = String(ahora.getMonth() + 1).padStart(2, '0')
  const dia = String(ahora.getDate()).padStart(2, '0')
  return `${ahora.getFullYear()}-${mes}-${dia}`
}

export const esFechaValida = (valor: string): boolean => {
  if (!FECHA.test(valor)) return false
  const [anio, mes, dia] = valor.split('-').map(Number)
  const fecha = new Date(anio, mes - 1, dia)
  return fecha.getFullYear() === anio && fecha.getMonth() === mes - 1 && fecha.getDate() === dia
}

const fecha = z.string().refine(esFechaValida, text.invalidDate)

/** Reglas del formulario de novedad (permiso, licencia o vacaciones): replican las del servidor que se pueden anticipar. */
export function esquemaDeNovedad(requisitos: ParametrosConservados | null) {
  return z
    .object({
      tipo: z.enum(['Permiso', 'Licencia', 'Vacaciones']),
      fecha_inicio: fecha,
      fecha_fin: fecha,
      motivo: z.string().max(MOTIVO_MAXIMO, text.motivoTooLong),
    })
    .superRefine((valores, ctx) => {
      if (esFechaValida(valores.fecha_inicio) && esFechaValida(valores.fecha_fin) && valores.fecha_fin < valores.fecha_inicio) {
        ctx.addIssue({ code: 'custom', path: ['fecha_fin'], message: text.endBeforeStart })
      }
      if (requisitos?.justificacion_obligatoria && valores.motivo.trim() === '') {
        ctx.addIssue({ code: 'custom', path: ['motivo'], message: text.motivoRequired })
      }
    })
}

export function esquemaDeAusencia(requisitos: ParametrosConservados | null) {
  return z
    .object({
      // La ausencia no elige tipo; el campo existe para compartir la forma del formulario de novedad.
      tipo: z.enum(['Permiso', 'Licencia', 'Vacaciones']),
      fecha_inicio: fecha,
      fecha_fin: fecha,
      motivo: z.string().max(MOTIVO_MAXIMO, text.motivoTooLong),
    })
    .superRefine((valores, ctx) => {
      if (esFechaValida(valores.fecha_inicio) && esFechaValida(valores.fecha_fin) && valores.fecha_fin < valores.fecha_inicio) {
        ctx.addIssue({ code: 'custom', path: ['fecha_fin'], message: text.endBeforeStart })
      }
      if (esFechaValida(valores.fecha_fin) && valores.fecha_fin > hoyIso()) {
        ctx.addIssue({ code: 'custom', path: ['fecha_fin'], message: text.futureAbsence })
      }
      if (requisitos?.justificacion_obligatoria && valores.motivo.trim() === '') {
        ctx.addIssue({ code: 'custom', path: ['motivo'], message: text.motivoRequired })
      }
    })
}

export const esquemaDeMovimiento = z.object({
  colaborador_id: z.number({ error: text.personRequired }).int().positive(text.personRequired),
  tipo: z.enum(['Ascenso', 'Traslado']),
  posicion_destino_id: z.number({ error: text.destinationRequired }).int().positive(text.destinationRequired),
  fecha_efectiva: fecha,
  motivo: z.string().trim().min(1, text.motivoRequired).max(MOTIVO_MAXIMO, text.motivoTooLong),
})

export const esquemaDeResolucion = z.object({
  justificacion: z.string().trim().min(1, text.reasonRequired).max(MOTIVO_MAXIMO, text.motivoTooLong),
})

export const esquemaDeRevisionDeAusencia = z.object({
  resultado: z.enum(['Revisada', 'Justificada']),
  observacion: z.string().trim().min(1, text.observationRequired).max(OBSERVACION_MAXIMA, text.observationTooLong),
})
