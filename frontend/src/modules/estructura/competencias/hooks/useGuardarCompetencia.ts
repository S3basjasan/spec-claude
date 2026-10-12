import { useMutation, useQueryClient } from '@tanstack/react-query'
import { actualizarCompetencia, crearCompetencia, type DatosCompetencia } from '../api'
import { competenciasKey } from './useCompetencias'

/** Crea una competencia. Las escrituras nunca se reintentan automáticamente. */
export function useCrearCompetencia() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosCompetencia) => crearCompetencia(datos),
    retry: false,
    // Sin esperar la recarga: quien llama navega apenas se guarda.
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: competenciasKey }),
  })
}

/** Actualiza una competencia enviando la versión con la que se abrió el formulario. */
export function useActualizarCompetencia(id: number, version: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosCompetencia) => actualizarCompetencia(id, datos, version),
    retry: false,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: competenciasKey }),
  })
}
