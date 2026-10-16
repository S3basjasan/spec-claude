import { z } from 'zod'
import { toApiError } from '../../../shared/api/httpClient'
import { categoriasText } from './text'

export const LIMITE_NOMBRE = 100

const text = categoriasText.validation

/** Replica las reglas de ReglasDeCategoria: obligatorio tras recortar y máximo 100 caracteres. La unicidad la decide el servidor. */
export const categoriaSchema = z.object({
  nombre: z.string().trim().min(1, text.required).max(LIMITE_NOMBRE, text.tooLong(LIMITE_NOMBRE)),
})

export interface CategoriaFormValues {
  nombre: string
}

export interface ProblemaDeGuardado {
  /** Primer mensaje del servidor para el campo `nombre` (422). */
  nombre: string | null
  /** Aviso general sobre el formulario; null si solo hay error de campo. */
  aviso: 'conflicto' | 'noExiste' | 'deshabilitada' | 'red' | 'inesperado' | null
}

/** Traduce un error de la API (422 de nombre, 409, 404, 503, red) a lo que muestra el formulario. */
export function problemaDeGuardado(error: unknown): ProblemaDeGuardado {
  const api = toApiError(error)
  const nombre = api.fieldErrors.nombre?.[0] ?? null

  if (api.isNetworkError) return { nombre, aviso: 'red' }
  if (api.status === 409) return { nombre, aviso: 'conflicto' }
  if (api.status === 404) return { nombre, aviso: 'noExiste' }
  if (api.code === 'ESCRITURA_DESHABILITADA') return { nombre, aviso: 'deshabilitada' }
  if (api.status === 422 && nombre !== null) return { nombre, aviso: null }
  return { nombre, aviso: 'inesperado' }
}
