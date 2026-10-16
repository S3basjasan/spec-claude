import { useMutation, useQueryClient } from '@tanstack/react-query'
import { actualizarPosicion, crearPosicion, type DatosPosicion } from '../api'
import { posicionesKey } from './usePosiciones'

/** Crea una posición. Las escrituras nunca se reintentan automáticamente. */
export function useCrearPosicion() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosPosicion) => crearPosicion(datos),
    retry: false,
    // Sin esperar la recarga: quien llama cierra el formulario apenas se guarda.
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: posicionesKey }),
  })
}

/** Actualiza una posición enviando la versión con la que se abrió el formulario. */
export function useActualizarPosicion(id: number, version: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosPosicion) => actualizarPosicion(id, datos, version),
    retry: false,
    // También tras un rechazo (404 o 409): la estructura que ve la persona puede haber cambiado.
    onSettled: () => void queryClient.invalidateQueries({ queryKey: posicionesKey }),
  })
}
