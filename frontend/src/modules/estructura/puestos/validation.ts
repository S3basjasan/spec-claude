import { z } from 'zod'
import { MAX_ELEMENTOS } from './api'
import { puestosText } from './text'

export const LIMITES = {
  nombre: 50,
  descripcion: 200,
  mision: 200,
  texto: 500,
  elementos: MAX_ELEMENTOS,
} as const

const text = puestosText.validation

/** Compara sin distinguir mayúsculas ni espacios al inicio o al final. */
const clave = (valor: string) => valor.trim().toLowerCase()

const funcion = z.object({
  tipo: z.enum(['Esencial', 'Complementaria']),
  descripcion: z.string().trim().min(1, text.functionRequired).max(LIMITES.texto, text.functionTooLong(LIMITES.texto)),
})

const requisito = z.object({
  tipo: z.enum(['Formacion', 'Experiencia', 'Capacitacion']),
  descripcion: z.string().trim().min(1, text.requirementRequired).max(LIMITES.texto, text.requirementTooLong(LIMITES.texto)),
})

const competencia = z.object({
  competencia_id: z.number({ error: text.competencyRequired }).int(text.competencyRequired),
  grado_requerido: z.string().min(1, text.gradeRequired),
})

/** Replica las reglas de GuardarPuestoRequest del backend: mínimos, máximos, esenciales y repeticiones. */
export const puestoSchema = z.object({
  nombre: z.string().trim().min(1, text.nameRequired).max(LIMITES.nombre, text.nameTooLong(LIMITES.nombre)),
  descripcion: z.string().trim().min(1, text.descriptionRequired).max(LIMITES.descripcion, text.descriptionTooLong(LIMITES.descripcion)),
  mision: z.string().trim().min(1, text.missionRequired).max(LIMITES.mision, text.missionTooLong(LIMITES.mision)),
  funciones: z
    .array(funcion)
    .min(1, text.functionsMin)
    .max(LIMITES.elementos, text.functionsMax(LIMITES.elementos))
    .superRefine((funciones, contexto) => {
      if (funciones.length > 0 && !funciones.some((f) => f.tipo === 'Esencial')) {
        contexto.addIssue({ code: 'custom', message: text.functionsEssential, path: [] })
      }
      const vistas = new Set<string>()
      funciones.forEach((f, indice) => {
        const k = clave(f.descripcion)
        if (k !== '' && vistas.has(k)) contexto.addIssue({ code: 'custom', message: text.functionDuplicate, path: [indice, 'descripcion'] })
        vistas.add(k)
      })
    }),
  requisitos: z
    .array(requisito)
    .min(1, text.requirementsMin)
    .max(LIMITES.elementos, text.requirementsMax(LIMITES.elementos))
    .superRefine((requisitos, contexto) => {
      if (requisitos.length > 0 && !requisitos.some((r) => r.tipo === 'Formacion')) {
        contexto.addIssue({ code: 'custom', message: text.requirementsFormation, path: [] })
      } else if (requisitos.length > 0 && !requisitos.some((r) => r.tipo === 'Experiencia')) {
        contexto.addIssue({ code: 'custom', message: text.requirementsExperience, path: [] })
      }
      const vistos = new Set<string>()
      requisitos.forEach((r, indice) => {
        const k = `${r.tipo}|${clave(r.descripcion)}`
        if (clave(r.descripcion) !== '' && vistos.has(k)) {
          contexto.addIssue({ code: 'custom', message: text.requirementDuplicate, path: [indice, 'descripcion'] })
        }
        vistos.add(k)
      })
    }),
  competencias: z
    .array(competencia)
    .min(1, text.competenciesMin)
    .max(LIMITES.elementos, text.competenciesMax(LIMITES.elementos))
    .superRefine((competencias, contexto) => {
      const vistas = new Set<number>()
      competencias.forEach((c, indice) => {
        if (vistas.has(c.competencia_id)) contexto.addIssue({ code: 'custom', message: text.competencyDuplicate, path: [indice, 'competencia_id'] })
        vistas.add(c.competencia_id)
      })
    }),
})

export type PuestoFormValues = z.infer<typeof puestoSchema>
