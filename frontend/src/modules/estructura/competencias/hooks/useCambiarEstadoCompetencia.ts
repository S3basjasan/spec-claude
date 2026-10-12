import { useMutation, useQueryClient } from '@tanstack/react-query'
import { cambiarEstadoCompetencia, type EstadoCompetencia } from '../api'
import { competenciasKey } from './useCompetencias'

/** Activa o desactiva una competencia. Las escrituras nunca se reintentan automáticamente. */
export function useCambiarEstadoCompetencia() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, estado }: { id: number; estado: EstadoCompetencia }) => cambiarEstadoCompetencia(id, estado),
    retry: false,
    // Aun si ya estaba en ese estado (422) o ya no existe (404), el listado debe refrescarse.
    onSettled: () => void queryClient.invalidateQueries({ queryKey: competenciasKey }),
  })
}
