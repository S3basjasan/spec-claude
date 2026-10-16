import { useQuery } from '@tanstack/react-query'
import { toApiError } from '../../../../shared/api/httpClient'
import { obtenerDesarrollo } from '../api'

export const desarrolloKey = (colaboradorId: number | null) =>
  colaboradorId === null ? (['mi-ficha', 'desarrollo'] as const) : (['colaboradores', 'desarrollo', colaboradorId] as const)

/** Un 4xx es una respuesta definitiva del servidor (404, 403): no se reintenta; el resto, una vez. */
const sinReintentoEn4xx = (intentos: number, error: unknown) => {
  const { status } = toApiError(error)
  return (status === undefined || status >= 500) && intentos < 1
}

/** Desarrollo profesional de una ficha (`colaboradorId`) o de la propia (`null`). Se consulta aparte de la ficha. */
export function useDesarrollo(colaboradorId: number | null) {
  return useQuery({
    queryKey: desarrolloKey(colaboradorId),
    queryFn: () => obtenerDesarrollo(colaboradorId),
    retry: sinReintentoEn4xx,
  })
}
