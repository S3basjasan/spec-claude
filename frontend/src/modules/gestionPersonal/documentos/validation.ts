import { z } from 'zod'
import { toApiError } from '../../../shared/api/httpClient'
import { MAX_BYTES } from './api'
import { documentosText } from './text'

export const LIMITE_TITULO = 200

const text = documentosText.validation

/** Replica las reglas del backend para el título y la categoría; el archivo se valida aparte por ser un `File`. */
export const documentoSchema = z.object({
  titulo: z.string().trim().min(1, text.required).max(LIMITE_TITULO, text.tooLong(LIMITE_TITULO)),
  categoria_id: z.string().min(1, text.categoryRequired),
  visible_colaborador: z.boolean(),
})

export interface DocumentoFormValues {
  titulo: string
  categoria_id: string
  visible_colaborador: boolean
}

/** Mensaje de error del archivo o null si es aceptable; el servidor decide por contenido y es la autoridad. */
export function errorDeArchivo(archivo: File | null): string | null {
  if (archivo === null) return text.fileRequired
  if (!/\.pdf$/i.test(archivo.name)) return text.notPdf
  if (archivo.size === 0) return text.empty
  if (archivo.size > MAX_BYTES) return text.tooBig
  return null
}

export type AvisoDeCarga = 'categoria' | 'clave' | 'almacenamiento' | 'red' | 'deshabilitada' | 'noExiste' | 'inesperado' | null

export interface ProblemaDeCarga {
  titulo: string | null
  categoria: string | null
  archivo: string | null
  /** El archivo fue rechazado: hay que volver a seleccionarlo. */
  reseleccionar: boolean
  aviso: AvisoDeCarga
}

const SIN_PROBLEMA: ProblemaDeCarga = { titulo: null, categoria: null, archivo: null, reseleccionar: false, aviso: null }

/** Traduce un error de la API de carga (422 por campo, 409, 413, 503, red) a lo que muestra el diálogo. */
export function problemaDeCarga(error: unknown): ProblemaDeCarga {
  const api = toApiError(error)
  const campos = api.fieldErrors

  if (api.isNetworkError) return { ...SIN_PROBLEMA, aviso: 'red' }
  if (api.status === 413) return { ...SIN_PROBLEMA, archivo: text.tooBig, reseleccionar: true }
  if (api.code === 'CATEGORIA_INACTIVA') return { ...SIN_PROBLEMA, aviso: 'categoria' }
  if (api.code === 'CLAVE_DE_OPERACION_REUTILIZADA') return { ...SIN_PROBLEMA, aviso: 'clave' }
  if (api.status === 404) return { ...SIN_PROBLEMA, aviso: 'noExiste' }
  if (api.code === 'ESCRITURA_DESHABILITADA') return { ...SIN_PROBLEMA, aviso: 'deshabilitada' }
  if (api.status === 503) return { ...SIN_PROBLEMA, aviso: 'almacenamiento' }
  if (api.status === 422) {
    const archivo = campos.archivo?.[0] ?? null
    const titulo = campos.titulo?.[0] ?? null
    const categoria = campos.categoria_id?.[0] ?? null
    if (archivo !== null || titulo !== null || categoria !== null) return { titulo, categoria, archivo, reseleccionar: archivo !== null, aviso: null }
  }
  return { ...SIN_PROBLEMA, aviso: 'inesperado' }
}

/** Tamaño legible («482 KB», «1,2 MB»). */
export function formatearTamano(bytes: number): string {
  if (bytes < 1000) return `${bytes} B`
  if (bytes < 1_000_000) return `${Math.round(bytes / 1000)} KB`
  return `${(bytes / 1_000_000).toFixed(1).replace('.', ',')} MB`
}
