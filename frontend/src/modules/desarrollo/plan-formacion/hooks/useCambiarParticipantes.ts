import { useMutation, useQueryClient } from '@tanstack/react-query'
import { cambiarParticipantes, cancelarAccion } from '../api'
import { accionesKey, planesKey } from './claves'

/** Reemplaza los participantes de una acción. Es una escritura: nunca se reintenta automáticamente. */
export function useCambiarParticipantes() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ accionId, colaboradorIds }: { accionId: number; colaboradorIds: number[] }) => cambiarParticipantes(accionId, colaboradorIds),
    onSuccess: () => {
      // Cambian el detalle, el historial, los cupos de las listas y los cruces de fechas.
      void queryClient.invalidateQueries({ queryKey: planesKey })
      void queryClient.invalidateQueries({ queryKey: accionesKey })
    },
  })
}

/** Cancela una acción de un plan Aprobado o Ejecutando, con motivo. */
export function useCancelarAccion() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ accionId, motivo }: { accionId: number; motivo: string }) => cancelarAccion(accionId, motivo),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: planesKey })
      void queryClient.invalidateQueries({ queryKey: accionesKey })
    },
  })
}
