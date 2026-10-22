import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { actualizarParametros, obtenerParametrosDeAdecuacion, type DatosParametros, type Parametros } from '../api'
import { adecuacionKey } from './claves'

export function useParametrosDeAdecuacion(page: number, enabled = true) {
  return useQuery({
    queryKey: [...adecuacionKey, 'parametros', page],
    queryFn: () => obtenerParametrosDeAdecuacion(page),
    enabled,
    placeholderData: keepPreviousData,
    refetchOnMount: 'always',
  })
}

/** La escritura nunca se reintenta automáticamente (Constitución II). */
export function useActualizarParametros() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosParametros): Promise<Parametros> => actualizarParametros(datos),
    retry: false,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: [...adecuacionKey, 'parametros'] })
      // Los pesos y el umbral nuevos rigen en la comparativa y en las siguientes adecuaciones.
      void queryClient.invalidateQueries({ queryKey: [...adecuacionKey, 'comparar'] })
      void queryClient.invalidateQueries({ queryKey: [...adecuacionKey, 'previsualizar'] })
    },
  })
}
