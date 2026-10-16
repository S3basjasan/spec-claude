import { z } from 'zod'
import { TIPOS_ACCION, type AccionBorrador, type TipoAccion } from './api'
import { planText } from './text'

const t1 = planText.asistente.paso1
const ta = planText.asistente.accion

/** Paso 1 (espejo de `GuardarPlanRequest`): nombre y año obligatorios; descripción opcional. */
export const paso1Schema = z.object({
  nombre: z.string().trim().min(1, t1.nombreRequerido).max(120, t1.nombreMax),
  anio: z
    .string()
    .trim()
    .min(1, t1.anioRequerido)
    .refine((v) => /^\d{4}$/.test(v) && Number(v) >= 2000 && Number(v) <= 2100, t1.anioInvalido),
  descripcion: z.string().max(2000, t1.descripcionMax),
})

export type ValoresPaso1 = z.infer<typeof paso1Schema>

export interface ErroresFormulario {
  [campo: string]: string | undefined
}

export function validarPaso1(valores: ValoresPaso1): ErroresFormulario {
  const resultado = paso1Schema.safeParse(valores)
  if (resultado.success) return {}
  const errores: ErroresFormulario = {}
  for (const issue of resultado.error.issues) {
    const campo = String(issue.path[0])
    errores[campo] ??= issue.message
  }
  return errores
}

/** Valores del formulario de una acción: los números se escriben como texto hasta validarlos. */
export interface ValoresAccion {
  nombre: string
  curso_id: number | null
  tipo: TipoAccion | ''
  fecha_inicio: string
  fecha_fin: string
  duracion_horas: string
  formador: string
  capacidad_maxima: string
  costo: string
  competencia_ids: number[]
}

export const ACCION_VACIA: ValoresAccion = {
  nombre: '',
  curso_id: null,
  tipo: '',
  fecha_inicio: '',
  fecha_fin: '',
  duracion_horas: '',
  formador: '',
  capacidad_maxima: '',
  costo: '',
  competencia_ids: [],
}

const numeroOpcional = (texto: string) => (texto.trim() === '' ? null : Number(texto.replace(',', '.')))
const fechaOpcional = z.string().regex(/^\d{4}-\d{2}-\d{2}$/).or(z.literal(''))

/**
 * Una acción en Borrador solo exige nombre y competencias objetivo; lo demás, si se escribe, debe ser válido:
 * fechas dentro del año del plan y fin no anterior al inicio, horas > 0, capacidad entero > 0 y costo ≥ 0.
 */
function esquemaAccion(anio: number) {
  return z
    .object({
      nombre: z.string().trim().min(1, ta.nombreRequerido).max(120),
      competencia_ids: z.array(z.number().int().positive()).min(1, ta.competenciasRequeridas),
      tipo: z.enum([...TIPOS_ACCION, '']),
      fecha_inicio: fechaOpcional,
      fecha_fin: fechaOpcional,
      duracion_horas: z.string(),
      formador: z.string().max(120),
      capacidad_maxima: z.string(),
      costo: z.string(),
    })
    .superRefine((v, ctx) => {
      for (const campo of ['fecha_inicio', 'fecha_fin'] as const) {
        if (v[campo] !== '' && Number(v[campo].slice(0, 4)) !== anio) {
          ctx.addIssue({ code: 'custom', path: [campo], message: ta.fueraDelAnio(anio) })
        }
      }
      if (v.fecha_inicio !== '' && v.fecha_fin !== '' && v.fecha_fin < v.fecha_inicio) {
        ctx.addIssue({ code: 'custom', path: ['fecha_fin'], message: ta.fechaFinAnterior })
      }
      const horas = numeroOpcional(v.duracion_horas)
      if (horas !== null && !(Number.isFinite(horas) && horas > 0)) {
        ctx.addIssue({ code: 'custom', path: ['duracion_horas'], message: ta.horasInvalidas })
      }
      const capacidad = numeroOpcional(v.capacidad_maxima)
      if (capacidad !== null && !(Number.isInteger(capacidad) && capacidad > 0)) {
        ctx.addIssue({ code: 'custom', path: ['capacidad_maxima'], message: ta.capacidadInvalida })
      }
      const costo = numeroOpcional(v.costo)
      if (costo !== null && !(Number.isFinite(costo) && costo >= 0)) {
        ctx.addIssue({ code: 'custom', path: ['costo'], message: ta.costoInvalido })
      }
    })
}

export type ResultadoAccion =
  | { ok: true; accion: Omit<AccionBorrador, 'id' | 'colaborador_ids'> }
  | { ok: false; errores: ErroresFormulario }

export function validarAccion(valores: ValoresAccion, anio: number): ResultadoAccion {
  const resultado = esquemaAccion(anio).safeParse(valores)
  if (!resultado.success) {
    const errores: ErroresFormulario = {}
    for (const issue of resultado.error.issues) {
      const campo = String(issue.path[0])
      errores[campo] ??= issue.message
    }
    return { ok: false, errores }
  }

  const v = resultado.data
  return {
    ok: true,
    accion: {
      nombre: v.nombre.trim(),
      curso_id: valores.curso_id,
      tipo: v.tipo === '' ? null : v.tipo,
      fecha_inicio: v.fecha_inicio === '' ? null : v.fecha_inicio,
      fecha_fin: v.fecha_fin === '' ? null : v.fecha_fin,
      duracion_horas: numeroOpcional(v.duracion_horas),
      formador: v.formador.trim() === '' ? null : v.formador.trim(),
      capacidad_maxima: numeroOpcional(v.capacidad_maxima),
      costo: numeroOpcional(v.costo),
      competencia_ids: v.competencia_ids,
    },
  }
}

/** Convierte una acción del servidor a valores de formulario. */
export function valoresDeAccion(accion: Omit<AccionBorrador, 'id' | 'colaborador_ids'>): ValoresAccion {
  return {
    nombre: accion.nombre,
    curso_id: accion.curso_id,
    tipo: accion.tipo ?? '',
    fecha_inicio: accion.fecha_inicio ?? '',
    fecha_fin: accion.fecha_fin ?? '',
    duracion_horas: accion.duracion_horas === null ? '' : String(accion.duracion_horas),
    formador: accion.formador ?? '',
    capacidad_maxima: accion.capacidad_maxima === null ? '' : String(accion.capacidad_maxima),
    costo: accion.costo === null ? '' : String(accion.costo),
    competencia_ids: accion.competencia_ids,
  }
}
