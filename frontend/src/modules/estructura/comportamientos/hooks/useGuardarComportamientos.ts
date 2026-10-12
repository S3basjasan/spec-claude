import { useMutation, useQueryClient } from '@tanstack/react-query'
import { actualizarComportamientos, crearComportamientos, type ComportamientosPorGrado } from '../api'
import { comportamientosKey } from './useComportamientos'

interface DatosCreacion {
  competenciaId: number
  comportamientos: ComportamientosPorGrado
}

/** Crea un registro. Las escrituras nunca se reintentan automáticamente. */
export function useCrearComportamientos() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ competenciaId, comportamientos }: DatosCreacion) => crearComportamientos(competenciaId, comportamientos),
    retry: false,
    // Sin esperar la recarga: quien llama navega apenas se guarda.
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: comportamientosKey }),
  })
}

/** Actualiza un registro enviando la versión con la que se abrió el formulario. */
export function useActualizarComportamientos(competenciaId: number, version: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (comportamientos: ComportamientosPorGrado) => actualizarComportamientos(competenciaId, comportamientos, version),
    retry: false,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: comportamientosKey }),
  })
}
