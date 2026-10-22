import { z } from 'zod'
import { MAX_ENUNCIADO, MAX_OPCIONES, MAX_PREGUNTAS, MAX_TEXTO_OPCION, MAX_TITULO, MIN_OPCIONES, TIPOS_MICRO, type DatosMicro, type PreguntaBorrador } from './api'
import { microText } from './text'

const t = microText.validation

/** Pregunta del constructor: enunciado obligatorio, de 2 a 5 opciones con texto y exactamente una correcta. */
export const preguntaSchema = z.object({
  enunciado: z.string().trim().min(1, t.statementRequired).max(MAX_ENUNCIADO, t.statementTooLong),
  opciones: z
    .array(
      z.object({
        texto: z.string().trim().min(1, t.optionRequired).max(MAX_TEXTO_OPCION, t.optionTooLong),
        es_correcta: z.boolean(),
      }),
    )
    .min(MIN_OPCIONES, t.optionsRange)
    .max(MAX_OPCIONES, t.optionsRange)
    .refine((opciones) => opciones.filter((o) => o.es_correcta).length === 1, t.oneCorrect),
})

/** Lo mínimo para guardar como Borrador: título, tipo y duración (siempre obligatorios). */
export const borradorSchema = z.object({
  titulo: z.string().trim().min(1, t.titleRequired).max(MAX_TITULO, t.titleTooLong),
  tipo: z.enum(TIPOS_MICRO as [string, ...string[]]),
  duracion_minutos: z.number({ error: t.durationInvalid }).int(t.durationInvalid).min(1, t.durationInvalid),
  url: z.string().trim().refine((valor) => valor === '' || /^https?:\/\/\S+$/i.test(valor), t.urlInvalid),
  miniatura_url: z.string().trim().refine((valor) => valor === '' || /^https?:\/\/\S+$/i.test(valor), t.thumbnailInvalid),
  preguntas: z.array(preguntaSchema).max(MAX_PREGUNTAS, t.questionsTooMany),
})

export interface BorradorMicro {
  titulo: string
  tipo: string
  /** Texto del campo: se convierte a número al validar. */
  duracion: string
  url: string
  miniatura_url: string
  competencia_ids: number[]
  preguntas: PreguntaBorrador[]
}

export type ErroresMicro = Record<string, string>

/** Convierte el texto de la duración a entero o NaN si no lo es. */
export function duracionDe(texto: string): number {
  const limpio = texto.trim()
  return /^\d+$/.test(limpio) ? Number(limpio) : Number.NaN
}

/**
 * Valida el formulario. `publicar` exige además URL (salvo Quiz), al menos una competencia y, en un Quiz, al menos una
 * pregunta. Devuelve los datos listos para enviar o los errores por campo (claves como `preguntas.0.enunciado`).
 */
export function validarMicro(b: BorradorMicro, publicar: boolean): { datos: DatosMicro } | { errores: ErroresMicro } {
  const esQuiz = b.tipo === 'Quiz'
  const resultado = borradorSchema.safeParse({
    titulo: b.titulo,
    tipo: b.tipo,
    duracion_minutos: duracionDe(b.duracion),
    url: esQuiz ? '' : b.url,
    miniatura_url: b.miniatura_url,
    preguntas: esQuiz ? b.preguntas : [],
  })

  const errores: ErroresMicro = {}
  if (!resultado.success) {
    for (const issue of resultado.error.issues) {
      const clave = issue.path.length === 0 ? 'general' : issue.path.map(String).join('.')
      // En opciones, la regla de una sola correcta se muestra en la pregunta y no en la lista de opciones.
      const destino = clave.endsWith('.opciones') && issue.message === t.oneCorrect ? clave.replace('.opciones', '.correcta') : clave
      if (!(destino in errores)) errores[destino] = issue.message
    }
  }

  if (publicar) {
    if (!esQuiz && b.url.trim() === '' && !('url' in errores)) errores.url = t.urlRequired
    if (b.competencia_ids.length === 0) errores.competencia_ids = t.competencesRequired
    if (esQuiz && b.preguntas.length === 0) errores.preguntas = t.questionsRequired
  }

  if (Object.keys(errores).length > 0 || !resultado.success) return { errores }

  return {
    datos: {
      titulo: resultado.data.titulo,
      tipo: b.tipo as DatosMicro['tipo'],
      duracion_minutos: resultado.data.duracion_minutos,
      url: esQuiz || resultado.data.url === '' ? null : resultado.data.url,
      miniatura_url: resultado.data.miniatura_url === '' ? null : resultado.data.miniatura_url,
      competencia_ids: b.competencia_ids,
      preguntas: esQuiz ? resultado.data.preguntas : null,
    },
  }
}

/** Una pregunta nueva con dos opciones vacías y la primera marcada como correcta. */
export function preguntaNueva(): PreguntaBorrador {
  return {
    enunciado: '',
    opciones: [
      { texto: '', es_correcta: true },
      { texto: '', es_correcta: false },
    ],
  }
}
