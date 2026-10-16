import { useMutation, useQueryClient } from '@tanstack/react-query'
import { generarPlan } from '../api'
import { dnfKey } from './claves'

/** Genera el borrador de Plan de Formación de una DNF. Es una escritura: nunca se reintenta automáticamente. */
export function useGenerarPlan() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (dnfId: number) => generarPlan(dnfId),
    onSuccess: () => {
      // El detalle y el historial pasan a mostrar el plan generado.
      void queryClient.invalidateQueries({ queryKey: dnfKey })
    },
  })
}
