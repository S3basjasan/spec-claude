import { z } from 'zod'
import { MOTIVOS } from './api'
import { offboardingText } from './text'

const text = offboardingText.validation
const FECHA = /^\d{4}-\d{2}-\d{2}$/

export const RAZON_MAXIMA = 1000
export const TEXTO_ABIERTO_MAXIMO = 2000

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

/** Formalización: reglas que se pueden anticipar; el servidor decide con la zona de la organización y el inicio del vínculo. */
export const esquemaDeSalida = z.object({
  colaborador_id: z.number({ error: text.personRequired }).int().positive(text.personRequired),
  motivo: z.enum(MOTIVOS as [string, ...string[]], { error: text.reasonRequired }),
  fecha_efectiva: z
    .string()
    .refine(esFechaValida, text.invalidDate)
    .refine((valor) => !esFechaValida(valor) || valor >= hoyIso(), text.pastDate),
})

export const esquemaDeCancelacion = z.object({
  razon: z.string().trim().min(1, text.cancelReasonRequired).max(RAZON_MAXIMA, text.cancelReasonTooLong),
})
