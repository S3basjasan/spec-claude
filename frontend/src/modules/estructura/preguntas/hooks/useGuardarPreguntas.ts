import { useMutation, useQueryClient } from '@tanstack/react-query'
import { actualizarPreguntas, crearPreguntas } from '../api'
import { preguntasKey } from './usePreguntas'

interface DatosCreacion {
  competenciaId: number
  preguntas: string[]
}

/** Crea un registro. Las escrituras nunca se reintentan automáticamente. */
export function useCrearPreguntas() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ competenciaId, preguntas }: DatosCreacion) => crearPreguntas(competenciaId, preguntas),
    retry: false,
    // Sin esperar la recarga: quien llama navega apenas se guarda.
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: preguntasKey }),
  })
}

/** Actualiza un registro enviando la versión con la que se abrió el formulario. */
export function useActualizarPreguntas(competenciaId: number, version: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (preguntas: string[]) => actualizarPreguntas(competenciaId, preguntas, version),
    retry: false,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: preguntasKey }),
  })
}
