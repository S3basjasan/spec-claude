import { useMutation, useQueryClient } from '@tanstack/react-query'
import { crearCompetencia, type DatosCompetencia } from '../api'
import { competenciasKey } from './useCompetencias'

/** Crea la competencia completa. Las escrituras nunca se reintentan automáticamente. */
export function useCrearCompetencia() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosCompetencia) => crearCompetencia(datos),
    retry: false,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: competenciasKey }),
  })
}
