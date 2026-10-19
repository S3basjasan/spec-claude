import { z } from 'zod'
import { CALIFICACION_MAX, CALIFICACION_MIN, MAX_COMENTARIO, MAX_INDICADOR, MAX_JUSTIFICACION, MAX_UNIDAD } from './api'
import { reportesText } from './text'

const ti = reportesText.formularioImpacto.errores
const te = reportesText.formularioEncuesta.errores
const ts = reportesText.formularioSeguimiento.errores

export type ErroresDe<T extends string> = Partial<Record<T, string>>

function primero(error: z.ZodError): Record<string, string> {
  const errores: Record<string, string> = {}
  for (const issue of error.issues) {
    const campo = String(issue.path[0] ?? '')
    if (campo !== '' && errores[campo] === undefined) errores[campo] = issue.message
  }
  return errores
}

const calificacion = (mensaje: string) =>
  z.number({ error: mensaje }).int(mensaje).min(CALIFICACION_MIN, mensaje).max(CALIFICACION_MAX, mensaje)

export const encuestaSchema = z.object({
  calificacion: calificacion(te.calificacionRequerida),
  comentario: z.string().max(MAX_COMENTARIO, te.comentarioLargo).optional(),
})

export const seguimientoSchema = z.object({
  aplicacion: calificacion(ts.aplicacionRequerida),
  apoyo: calificacion(ts.apoyoRequerido),
  comentario: z.string().max(MAX_COMENTARIO, ts.comentarioLargo).optional(),
})

export interface BorradorEncuesta {
  calificacion: number | null
  comentario: string
}

export interface BorradorSeguimiento {
  aplicacion: number | null
  apoyo: number | null
  comentario: string
}

export type CampoEncuesta = 'calificacion' | 'comentario'
export type CampoSeguimiento = 'aplicacion' | 'apoyo' | 'comentario'

export function validarEncuesta(b: BorradorEncuesta): ErroresDe<CampoEncuesta> {
  const r = encuestaSchema.safeParse({ calificacion: b.calificacion ?? undefined, comentario: b.comentario })
  return r.success ? {} : primero(r.error)
}

export function validarSeguimiento(b: BorradorSeguimiento): ErroresDe<CampoSeguimiento> {
  const r = seguimientoSchema.safeParse({ aplicacion: b.aplicacion ?? undefined, apoyo: b.apoyo ?? undefined, comentario: b.comentario })
  return r.success ? {} : primero(r.error)
}

/** Texto numérico de un campo de formulario: admite coma o punto decimal; vacío o no numérico da `null`. */
export function aNumero(texto: string): number | null {
  const limpio = texto.trim().replace(',', '.')
  if (limpio === '') return null
  const n = Number(limpio)
  return Number.isFinite(n) ? n : null
}

export interface BorradorImpacto {
  indicador: string
  unidad: string
  valor_antes: string
  valor_despues: string
  beneficio_estimado: string
  justificacion: string
}

export type CampoImpacto = keyof BorradorImpacto

const numeroDeTexto = z.string().transform((v, ctx) => {
  const n = aNumero(v)
  if (n === null) {
    ctx.addIssue({ code: 'custom', message: ti.numeroInvalido })
    return z.NEVER
  }
  return n
})

export const impactoSchema = z.object({
  indicador: z.string().trim().min(1, ti.indicadorRequerido).max(MAX_INDICADOR, ti.indicadorLargo),
  unidad: z.string().trim().min(1, ti.unidadRequerida).max(MAX_UNIDAD, ti.unidadLarga),
  valor_antes: numeroDeTexto,
  valor_despues: numeroDeTexto,
  beneficio_estimado: numeroDeTexto.refine((n) => n >= 0, ti.beneficioNegativo),
  justificacion: z.string().trim().min(1, ti.justificacionRequerida).max(MAX_JUSTIFICACION, ti.justificacionLarga),
})

/** Errores por campo del formulario de impacto; vacío si los datos son válidos. */
export function validarImpacto(b: BorradorImpacto): ErroresDe<CampoImpacto> {
  const r = impactoSchema.safeParse(b)
  return r.success ? {} : primero(r.error)
}

/** Datos listos para enviar (números ya convertidos); `null` si el borrador no es válido. */
export function datosDeImpacto(b: BorradorImpacto) {
  const r = impactoSchema.safeParse(b)
  return r.success ? r.data : null
}

/** ROI de la acción: (beneficio − costo) / costo × 100; `null` si el costo es 0 o desconocido (no aplica). */
export function calcularRoi(beneficio: number | null, costo: number | null): number | null {
  if (beneficio === null || costo === null || costo === 0) return null
  return ((beneficio - costo) / costo) * 100
}

export function tieneErrores(errores: object): boolean {
  return Object.keys(errores).length > 0
}
