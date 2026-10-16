import { useMutation, useQueryClient } from '@tanstack/react-query'
import { actualizarPlan, crearPlan, type PlanBorrador } from '../api'
import { accionesKey, planesKey } from './claves'

/** Crea (sin `id`) o actualiza (con `id`) un plan en Borrador. Es una escritura: nunca se reintenta automáticamente. */
export function useGuardarPlan() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, cuerpo }: { id?: number; cuerpo: PlanBorrador }) => (id === undefined ? crearPlan(cuerpo) : actualizarPlan(id, cuerpo)),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: planesKey })
      void queryClient.invalidateQueries({ queryKey: accionesKey })
    },
  })
}
