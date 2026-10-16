import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { toApiError } from '../../../../shared/api/httpClient'
import { listarDocumentos, listarMisDocumentos, type FiltrosDocumentos } from '../api'

export const documentosKey = ['documentos'] as const

/** Un 4xx es una respuesta definitiva del servidor (403, 404): no se reintenta; el resto, una vez. */
const sinReintentoEn4xx = (intentos: number, error: unknown) => {
  const { status } = toApiError(error)
  return (status === undefined || status >= 500) && intentos < 1
}

/** Documentos de una ficha (`colaboradorId`) o los propios (`null`); React Query cancela la petición previa con `signal`. */
export function useDocumentos(colaboradorId: number | null, filtros: FiltrosDocumentos) {
  return useQuery({
    queryKey: [...documentosKey, colaboradorId ?? 'propios', filtros],
    queryFn: ({ signal }) => (colaboradorId === null ? listarMisDocumentos(filtros, signal) : listarDocumentos(colaboradorId, filtros, signal)),
    placeholderData: keepPreviousData,
    retry: sinReintentoEn4xx,
  })
}
