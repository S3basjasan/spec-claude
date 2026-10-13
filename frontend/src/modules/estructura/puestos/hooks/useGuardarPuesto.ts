import { useMutation, useQueryClient } from '@tanstack/react-query'
import { actualizarPuesto, crearPuesto, type DatosPuesto } from '../api'
import { puestosKey } from './usePuestos'

/** Crea un puesto. Las escrituras nunca se reintentan automáticamente. */
export function useCrearPuesto() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosPuesto) => crearPuesto(datos),
    retry: false,
    // Sin esperar la recarga: quien llama navega apenas se guarda.
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: puestosKey }),
  })
}

/** Actualiza un puesto enviando la versión con la que se abrió el formulario. */
export function useActualizarPuesto(id: number, version: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosPuesto) => actualizarPuesto(id, datos, version),
    retry: false,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: puestosKey }),
  })
}
