import { useMutation, useQueryClient } from '@tanstack/react-query'
import { aplicarEscala } from '../api'
import { competenciasKey } from './useCompetencias'

/** Aplica otra escala a la competencia (solo cambian los nombres de los grados). Sin reintentos. */
export function useAplicarEscala() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, tipoGradoId }: { id: number; tipoGradoId: number }) => aplicarEscala(id, tipoGradoId),
    retry: false,
    onSettled: () => void queryClient.invalidateQueries({ queryKey: competenciasKey }),
  })
}
