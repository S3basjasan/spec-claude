import { z } from 'zod'
import { competenciasText } from './text'

const MAX_NOMBRE = 50
const MAX_TEXTO = 500
const MAX_COMPORTAMIENTO = 300
const MAX_CATEGORIA = 20

const mensajes = competenciasText.validation

/** Nombre sin tildes, en minúsculas y recortado: las categorías de una competencia no se repiten con esa clave. */
export function claveDeTexto(texto: string): string {
  return texto
    .normalize('NFD')
    .replace(/\p{Mn}/gu, '')
    .toLowerCase()
    .trim()
}

const texto = (vacio: string, max: number, largo: string) => z.string().trim().min(1, vacio).max(max, largo)

const grado = texto(mensajes.grades, MAX_TEXTO, mensajes.gradeMax)
const comportamiento = texto(mensajes.behaviors, MAX_COMPORTAMIENTO, mensajes.behaviorMax)
const pregunta = texto(mensajes.questions, MAX_TEXTO, mensajes.questionMax)

const cuatro = <T extends z.ZodType>(elemento: T) => z.array(elemento).length(4)

/** Datos de la competencia y sus grados; los comparten la creación y la edición de la sección Competencia. */
const camposDeCompetencia = {
  nombre: texto(mensajes.name, MAX_NOMBRE, mensajes.nameMax),
  descripcion: texto(mensajes.description, MAX_TEXTO, mensajes.descriptionMax),
  tipo: z.enum(['Cardinal', 'Especifica'], { error: mensajes.type }),
  tipoGradoId: z.number({ error: mensajes.scale }).int(mensajes.scale).positive(mensajes.scale),
  /** Nombre de la escala elegida: solo sirve para saber si hay que validar las categorías. */
  escala: z.string(),
  categorias: z.array(z.string()),
  grados: cuatro(grado),
}

type CamposConCategorias = { escala: string; categorias: string[] }

/** Con la escala Categorías, los cuatro nombres son obligatorios, de hasta 20 caracteres y sin repetirse. */
function validarCategorias(valores: CamposConCategorias, ctx: z.RefinementCtx) {
  if (valores.escala !== 'Categorías') return

  const limpios = valores.categorias.map((nombre) => nombre.trim())
  limpios.forEach((nombre, indice) => {
    if (nombre === '') ctx.addIssue({ code: 'custom', path: ['categorias', indice], message: mensajes.categories })
    else if (nombre.length > MAX_CATEGORIA) ctx.addIssue({ code: 'custom', path: ['categorias', indice], message: mensajes.categoryMax })
  })

  const claves = limpios.filter((nombre) => nombre !== '').map(claveDeTexto)
  if (new Set(claves).size !== claves.length) {
    limpios.forEach((nombre, indice) => {
      if (nombre !== '' && claves.filter((clave) => clave === claveDeTexto(nombre)).length > 1) {
        ctx.addIssue({ code: 'custom', path: ['categorias', indice], message: mensajes.categories })
      }
    })
  }
}

/** Replica las reglas de GuardarCompetenciaRequest (creación) del backend. */
export const competenciaCompletaSchema = z
  .object({
    ...camposDeCompetencia,
    comportamientos: cuatro(cuatro(comportamiento)),
    preguntas: cuatro(pregunta),
  })
  .superRefine(validarCategorias)

/** Edición de la sección Competencia: datos, escala y grados. */
export const seccionCompetenciaSchema = z.object(camposDeCompetencia).superRefine(validarCategorias)

export const seccionComportamientosSchema = z.object({ comportamientos: cuatro(cuatro(comportamiento)) })

export const seccionPreguntasSchema = z.object({ preguntas: cuatro(pregunta) })

export type CompetenciaCompletaValues = z.infer<typeof competenciaCompletaSchema>
export type SeccionCompetenciaValues = z.infer<typeof seccionCompetenciaSchema>
export type SeccionComportamientosValues = z.infer<typeof seccionComportamientosSchema>
export type SeccionPreguntasValues = z.infer<typeof seccionPreguntasSchema>

export const LIMITES = {
  nombre: MAX_NOMBRE,
  texto: MAX_TEXTO,
  comportamiento: MAX_COMPORTAMIENTO,
  categoria: MAX_CATEGORIA,
} as const

/**
 * Traduce un error por campo del backend (`tipo_grado_id`, `grados.2`, `comportamientos.1.3`…) a la ruta del
 * formulario. Los errores de un grupo entero (`grados`, `comportamientos.1`) quedan en el primer elemento.
 */
export function rutaDeCampo(campo: string): string {
  if (campo === 'tipo_grado_id') return 'tipoGradoId'
  if (campo === 'categorias') return 'categorias.0'
  if (campo === 'grados') return 'grados.0'
  if (campo === 'preguntas') return 'preguntas.0'
  if (campo === 'comportamientos') return 'comportamientos.0.0'
  if (/^comportamientos\.\d+$/.test(campo)) return `${campo}.0`
  return campo
}
