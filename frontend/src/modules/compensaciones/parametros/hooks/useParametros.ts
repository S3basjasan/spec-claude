import { useQuery } from '@tanstack/react-query'
import { toApiError } from '../../../../shared/api/httpClient'
import { listarParametros } from '../api'

export const parametrosKey = ['parametros-modulo'] as const

/**
 * Los valores no se refrescan al volver a la ventana: lo que el Gestor está escribiendo no debe perderse. Se actualizan al
 * guardar o con «Recargar».
 */
export function useParametros(habilitada = true) {
  return useQuery({
    queryKey: parametrosKey,
    queryFn: ({ signal }) => listarParametros(signal),
    enabled: habilitada,
    refetchOnWindowFocus: false,
    retry: (intentos, error) => {
      const { status } = toApiError(error)
      return (status === undefined || status >= 500) && intentos < 1
    },
  })
}
