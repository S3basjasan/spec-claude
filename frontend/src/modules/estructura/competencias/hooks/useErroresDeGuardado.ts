import { useState } from 'react'
import type { FieldPath, FieldValues, UseFormSetError } from 'react-hook-form'
import { toApiError } from '../../../../shared/api/httpClient'
import { rutaDeCampo } from '../validation'

export type Problema = 'red' | 'conflicto' | 'inesperado' | null

const CAMPOS_CONOCIDOS = /^(nombre|descripcion|tipo|tipoGradoId|categorias|grados|comportamientos|preguntas)(\.|$)/

/**
 * Traduce el error de una escritura: la red caída, el conflicto de versión (409) y los errores por campo (422),
 * que se muestran junto al campo del formulario. Lo que no se reconoce queda como error inesperado.
 */
export function useErroresDeGuardado<T extends FieldValues>(setError: UseFormSetError<T>) {
  const [problema, setProblema] = useState<Problema>(null)
  /** Error del servidor sobre el nombre (p. ej. duplicado), que el esquema local no puede conocer. */
  const [errorNombre, setErrorNombre] = useState<string>()

  const limpiar = () => {
    setProblema(null)
    setErrorNombre(undefined)
  }

  const manejar = (error: unknown) => {
    const api = toApiError(error)
    if (api.isNetworkError) return setProblema('red')
    if (api.status === 409) return setProblema('conflicto')

    if (api.status === 422) {
      let alguno = false
      for (const [campo, mensajes] of Object.entries(api.fieldErrors)) {
        const ruta = rutaDeCampo(campo)
        if (!CAMPOS_CONOCIDOS.test(ruta)) continue
        alguno = true
        if (ruta === 'nombre') setErrorNombre(mensajes[0])
        else setError(ruta as FieldPath<T>, { message: mensajes[0] })
      }
      if (alguno) return
    }
    setProblema('inesperado')
  }

  return { problema, errorNombre, limpiar, manejar }
}
