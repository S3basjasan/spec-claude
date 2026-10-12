import axios, { isAxiosError } from 'axios'
import { tokenStorage } from './tokenStorage'

const SESSION_EXPIRED_EVENT = 'hrms:session-expired'

/** Cliente HTTP único de la aplicación. Es el único lugar que conoce el token Bearer. */
export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000',
  headers: { Accept: 'application/json' },
})

http.interceptors.request.use((config) => {
  const token = tokenStorage.get()
  if (token) {
    config.headers.set('Authorization', `Bearer ${token}`)
  }
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    // Un 401 con token enviado significa sesión inválida o revocada.
    if (isAxiosError(error) && error.response?.status === 401 && error.config?.headers.has('Authorization')) {
      tokenStorage.clear()
      window.dispatchEvent(new Event(SESSION_EXPIRED_EVENT))
    }
    return Promise.reject(error)
  },
)

/** Suscribe un callback al evento de sesión expirada. Devuelve la función para cancelar. */
export function onSessionExpired(callback: () => void): () => void {
  window.addEventListener(SESSION_EXPIRED_EVENT, callback)
  return () => window.removeEventListener(SESSION_EXPIRED_EVENT, callback)
}

export interface ApiError {
  /** Código HTTP; ausente si no hubo respuesta (red caída, servidor apagado). */
  status?: number
  /** Mensaje del servidor, solo para códigos que lo definen en español (401, 403, 422). */
  message?: string
  /** Errores por campo de una respuesta 422. */
  fieldErrors: Record<string, string[]>
  /** true cuando no hubo respuesta del servidor. */
  isNetworkError: boolean
}

interface ErrorBody {
  message?: unknown
  errors?: unknown
}

/** Normaliza cualquier error de la capa HTTP a una forma estable para los hooks y páginas. */
export function toApiError(error: unknown): ApiError {
  if (!isAxiosError<ErrorBody>(error) || !error.response) {
    return { fieldErrors: {}, isNetworkError: true }
  }
  const { status, data } = error.response
  const fieldErrors: Record<string, string[]> = {}
  if (data && typeof data.errors === 'object' && data.errors !== null) {
    for (const [field, messages] of Object.entries(data.errors)) {
      if (Array.isArray(messages)) {
        fieldErrors[field] = messages.filter((m): m is string => typeof m === 'string')
      }
    }
  }
  return {
    status,
    message: typeof data?.message === 'string' ? data.message : undefined,
    fieldErrors,
    isNetworkError: false,
  }
}
