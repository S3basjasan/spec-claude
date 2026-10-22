import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { listarCompetencias, obtenerCompetencia, type TipoCompetencia } from '../api'

export const competenciasKey = ['competencias'] as const

/** Competencias por página: múltiplo de dos para la cuadrícula de dos columnas. */
export const COMPETENCIAS_POR_PAGINA = 12

export function useCompetencias(filtros: { q: string; tipo?: TipoCompetencia; page: number }) {
  return useQuery({
    queryKey: [...competenciasKey, 'lista', filtros],
    queryFn: () => listarCompetencias({ ...filtros, per_page: COMPETENCIAS_POR_PAGINA }),
    placeholderData: keepPreviousData,
  })
}

/**
 * Detalle con grados, comportamientos y preguntas; `enabled` permite pedirlo solo cuando hace falta.
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
