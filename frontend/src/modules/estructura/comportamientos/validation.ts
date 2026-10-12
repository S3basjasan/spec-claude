import { z } from 'zod'
import { comportamientosText } from './text'

export const LIMITES = { texto: 300, minimo: 1, maximo: 4 } as const

const text = comportamientosText.validation

const comportamiento = z.string().trim().min(1, text.empty).max(LIMITES.texto, text.tooLong(LIMITES.texto))

/** Un grado: de 1 a 4 textos, cada uno distinto de los demás del grado (sin distinguir mayúsculas). */
const grado = z
  .array(comportamiento)
  .min(LIMITES.minimo, text.min)
  .max(LIMITES.maximo, text.max(LIMITES.maximo))
  .superRefine((textos, contexto) => {
    const vistos = new Set<string>()
    textos.forEach((valor, indice) => {
      const clave = valor.trim().toLowerCase()
      if (clave !== '' && vistos.has(clave)) {
        contexto.addIssue({ code: 'custom', message: text.duplicate, path: [indice] })
      }
      vistos.add(clave)
    })
  })

/** Replica las reglas de GuardarComportamientosRequest del backend. */
export const comportamientosSchema = z.object({
  competencia_id: z.number({ error: text.competencia }).int(text.competencia),
  comportamientos: z.object({ A: grado, B: grado, C: grado, D: grado }),
})

export type ComportamientosFormValues = z.infer<typeof comportamientosSchema>
