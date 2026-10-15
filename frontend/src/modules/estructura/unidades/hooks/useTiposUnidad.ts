import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { crearTipo, guardarCadena, listarTipos, type BorradorDeTipo } from '../api'
import { unidadesKey } from './useUnidades'

export const tiposKey = ['tipos-unidad'] as const

/** La cadena de tipos por nivel, con su token de edición simultánea. */
export function useTiposUnidad() {
  return useQuery({ queryKey: tiposKey, queryFn: listarTipos })
}

/** Lectura nueva de la cadena (al abrir el editor): no debe partir de datos en caché. */
export function useCadenaFresca(enabled = true) {
  return useQuery({ queryKey: [...tiposKey, 'fresca'], queryFn: listarTipos, enabled, refetchOnMount: 'always', gcTime: 0 })
}

/** Agrega un tipo al final de la cadena. Las escrituras nunca se reintentan automáticamente. */
export function useCrearTipo() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (nombre: string) => crearTipo(nombre),
    retry: false,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: tiposKey })
      void queryClient.invalidateQueries({ queryKey: unidadesKey })
    },
  })
}

/** Guarda renombres y reordenamiento en una sola operación. */
export function useGuardarCadena() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ version, tipos }: { version: string; tipos: BorradorDeTipo[] }) => guardarCadena(version, tipos),
    retry: false,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: tiposKey })
      void queryClient.invalidateQueries({ queryKey: unidadesKey })
    },
  })
}
