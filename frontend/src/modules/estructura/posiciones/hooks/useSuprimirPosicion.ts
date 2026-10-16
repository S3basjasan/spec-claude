import { useMutation, useQueryClient } from '@tanstack/react-query'
import { suprimirPosicion } from '../api'
import { posicionesKey } from './usePosiciones'

/** Suprime una posición (definitivo). Las escrituras nunca se reintentan automáticamente. */
export function useSuprimirPosicion() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => suprimirPosicion(id),
    retry: false,
    // También tras un rechazo (404 o 422): la posición puede haber cambiado desde que se abrió el detalle.
    onSettled: () => void queryClient.invalidateQueries({ queryKey: posicionesKey }),
  })
}
