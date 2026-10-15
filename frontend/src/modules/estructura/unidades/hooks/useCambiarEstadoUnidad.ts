import { useMutation, useQueryClient } from '@tanstack/react-query'
import { cambiarEstadoUnidad, type EstadoUnidad } from '../api'
import { unidadesKey } from './useUnidades'

interface Cambio {
  id: number
  estado: EstadoUnidad
}

/** Desactiva o reactiva una unidad. Las escrituras nunca se reintentan automáticamente. */
export function useCambiarEstadoUnidad() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, estado }: Cambio) => cambiarEstadoUnidad(id, estado),
    retry: false,
    // También tras un rechazo (404 o 422): las cantidades y el estado que ve la persona pueden haber cambiado.
    onSettled: () => void queryClient.invalidateQueries({ queryKey: unidadesKey }),
  })
}
