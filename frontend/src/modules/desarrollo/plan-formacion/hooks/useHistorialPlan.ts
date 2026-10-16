import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { listarHistorial } from '../api'
import { planesKey } from './claves'

/** Una página del historial de un plan (más reciente primero). */
export function useHistorialPlan(planId: number, page: number) {
  return useQuery({
    queryKey: [...planesKey, 'historial', planId, page],
    queryFn: () => listarHistorial(planId, page, 10),
    placeholderData: keepPreviousData,
  })
}
