import { useMutation, useQueryClient } from '@tanstack/react-query'
import { cambiarEstadoPlan, reasignarAprobador, type EstadoDestino } from '../api'
import { accionesKey, planesKey } from './claves'

/** Cambia el estado del plan (enviar, aprobar, devolver, iniciar, cerrar). Es una escritura: nunca se reintenta. */
export function useCambiarEstado() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, estado, motivo }: { id: number; estado: EstadoDestino; motivo?: string }) => cambiarEstadoPlan(id, estado, motivo),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: planesKey })
      void queryClient.invalidateQueries({ queryKey: accionesKey })
    },
  })
}

/** Reasigna el aprobador de un plan en Revisión (el estado no cambia). */
export function useReasignarAprobador() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, aprobadorId }: { id: number; aprobadorId: number }) => reasignarAprobador(id, aprobadorId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: planesKey })
    },
  })
}
