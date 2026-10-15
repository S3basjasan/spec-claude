import { useMutation, useQueryClient } from '@tanstack/react-query'
import { actualizarUnidad, crearUnidad, type DatosUnidad } from '../api'
import { unidadesKey } from './useUnidades'

/** Crea una unidad. Las escrituras nunca se reintentan automáticamente. */
export function useCrearUnidad() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosUnidad) => crearUnidad(datos),
    retry: false,
    // Sin esperar la recarga: quien llama cierra el formulario apenas se guarda.
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: unidadesKey }),
  })
}

/** Actualiza una unidad enviando la versión con la que se abrió el formulario. */
export function useActualizarUnidad(id: number, version: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosUnidad) => actualizarUnidad(id, datos, version),
    retry: false,
    // También tras un rechazo (404 o 409): el árbol que ve la persona puede haber cambiado.
    onSettled: () => void queryClient.invalidateQueries({ queryKey: unidadesKey }),
  })
}
