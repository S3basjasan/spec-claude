import { z } from 'zod'
import { MAX_DESCRIPCION_RUTA, MAX_EXPERIENCIA, MAX_MOTIVO, MAX_NOMBRE_NIVEL, MAX_NOMBRE_RUTA, TIPOS_RUTA, type TipoRuta } from './api'
import { rutasText } from './text'

const t = rutasText

export type ErroresDe<T extends string> = Partial<Record<T, string>>
export type CampoRuta = 'nombre' | 'tipo' | 'descripcion' | 'departamento_id'
export type CampoNivel = 'nombre' | 'experiencia'

function primero(error: z.ZodError): Record<string, string> {
  const errores: Record<string, string> = {}
  for (const issue of error.issues) {
    const campo = String(issue.path[0] ?? '')
    if (campo !== '' && errores[campo] === undefined) errores[campo] = issue.message
  }
  return errores
}

export const rutaSchema = z
  .object({
    nombre: z.string().trim().min(1, 'El nombre es obligatorio.').max(MAX_NOMBRE_RUTA, `El nombre no puede superar los ${MAX_NOMBRE_RUTA} caracteres.`),
    tipo: z.enum(TIPOS_RUTA as [TipoRuta, ...TipoRuta[]], { error: 'Elige el tipo de ruta.' }),
    descripcion: z.string().max(MAX_DESCRIPCION_RUTA, `La descripción no puede superar los ${MAX_DESCRIPCION_RUTA} caracteres.`),
    departamento_id: z.number().nullable(),
  })
  .superRefine((valor, ctx) => {
    if (valor.tipo === 'Interareas' && valor.departamento_id !== null) {
      ctx.addIssue({ code: 'custom', path: ['departamento_id'], message: t.formulario.departamentoInterareas })
    }
  })

export interface BorradorRuta {
  nombre: string
  tipo: TipoRuta | ''
  descripcion: string
  departamento_id: number | null
}

export function validarRuta(borrador: BorradorRuta): ErroresDe<CampoRuta> {
  const r = rutaSchema.safeParse(borrador)
  return r.success ? {} : primero(r.error)
}

export const nivelSchema = z.object({
  nombre: z.string().trim().min(1, 'El nombre del nivel es obligatorio.').max(MAX_NOMBRE_NIVEL, `El nombre no puede superar los ${MAX_NOMBRE_NIVEL} caracteres.`),
  experiencia: z.number({ error: 'La experiencia mínima debe ser un número.' }).min(0, `La experiencia mínima debe estar entre 0 y ${MAX_EXPERIENCIA} años.`).max(MAX_EXPERIENCIA, `La experiencia mínima debe estar entre 0 y ${MAX_EXPERIENCIA} años.`).nullable(),
})

export interface BorradorNivel {
  nombre: string
  /** Texto del campo; vacío = sin experiencia mínima. */
  experiencia: string
}

/** Convierte el texto del campo a número (admite coma decimal); `null` si está vacío y `NaN` si no es un número. */
export function aNumero(texto: string): number | null {
  const limpio = texto.trim().replace(',', '.')
  return limpio === '' ? null : Number(limpio)
}

export function validarNivel(borrador: BorradorNivel): ErroresDe<CampoNivel> {
  const r = nivelSchema.safeParse({ nombre: borrador.nombre, experiencia: aNumero(borrador.experiencia) })
  return r.success ? {} : primero(r.error)
}

export const motivoSchema = z.string().trim().min(1, t.retirar.motivoRequerido).max(MAX_MOTIVO, t.retirar.motivoLargo)

/** Mensaje de error del motivo de retiro, o `undefined` si es válido. */
export function validarMotivo(motivo: string): string | undefined {
  const r = motivoSchema.safeParse(motivo)
  return r.success ? undefined : r.error.issues[0]?.message
}

export function tieneErrores(errores: object): boolean {
  return Object.values(errores).some((e) => e !== undefined)
}
