import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { listarCompetencias, obtenerCompetencia, type FiltroEstado, type TipoCompetencia } from '../api'

export const competenciasKey = ['competencias'] as const

export function useCompetencias(filtros: { q: string; tipo?: TipoCompetencia; estado?: FiltroEstado; page: number }) {
  return useQuery({
    queryKey: [...competenciasKey, 'lista', filtros],
    queryFn: () => listarCompetencias(filtros),
    placeholderData: keepPreviousData,
  })
}

/**
 * Detalle con los cuatro grados; `enabled` permite pedirlo solo cuando hace falta.
 * `siempreFresca` fuerza una lectura nueva al montar (la edición no debe partir de datos en caché).
 */
export function useCompetencia(id: number, enabled = true, siempreFresca = false) {
  return useQuery({
    queryKey: [...competenciasKey, 'detalle', id],
    queryFn: () => obtenerCompetencia(id),
    enabled,
    refetchOnMount: siempreFresca ? 'always' : true,
  })
}
