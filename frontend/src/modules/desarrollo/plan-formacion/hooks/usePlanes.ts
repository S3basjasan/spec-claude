import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { listarPlanes, obtenerPlan, type FiltrosPlanes } from '../api'
import { planesKey } from './claves'

/** Página de la tabla de planes con sus filtros. */
export function usePlanes(filtros: FiltrosPlanes) {
  return useQuery({
    queryKey: [...planesKey, 'lista', filtros],
    queryFn: () => listarPlanes(filtros),
    placeholderData: keepPreviousData,
  })
}

/** Detalle de un plan (acciones, participantes, totales, avisos y botones). */
export function usePlan(id: number | null) {
  return useQuery({
    queryKey: [...planesKey, 'detalle', id],
    queryFn: () => obtenerPlan(id as number),
    enabled: id !== null,
    retry: false,
  })
}
