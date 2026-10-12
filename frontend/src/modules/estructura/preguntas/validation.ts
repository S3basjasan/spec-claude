import { z } from 'zod'
import { CANTIDAD_PREGUNTAS } from './api'
import { preguntasText } from './text'

export const LIMITES = { texto: 500, cantidad: CANTIDAD_PREGUNTAS } as const

const text = preguntasText.validation

const pregunta = z.string().trim().min(1, text.empty).max(LIMITES.texto, text.tooLong(LIMITES.texto))

/** Replica las reglas de GuardarPreguntasRequest del backend: cuatro preguntas distintas entre sí. */
export const preguntasSchema = z.object({
  competencia_id: z.number({ error: text.competencia }).int(text.competencia),
  preguntas: z
    .array(pregunta)
    .length(LIMITES.cantidad, text.count)
    .superRefine((textos, contexto) => {
      const vistas = new Set<string>()
      textos.forEach((valor, indice) => {
        const clave = valor.trim().toLowerCase()
        if (clave !== '' && vistas.has(clave)) {
          contexto.addIssue({ code: 'custom', message: text.duplicate, path: [indice] })
        }
        vistas.add(clave)
      })
    }),
})

export type PreguntasFormValues = z.infer<typeof preguntasSchema>
