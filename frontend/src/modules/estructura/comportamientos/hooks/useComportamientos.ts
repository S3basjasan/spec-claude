import { keepPreviousData, useQuery } from '@tanstack/react-query'
import {
  listarCompetenciasDisponibles,
  listarComportamientos,
  obtenerComportamientos,
  type FiltroEstado,
  type TipoCompetencia,
} from '../api'

export const comportamientosKey = ['comportamientos'] as const

export function useComportamientos(filtros: { q: string; tipo?: TipoCompetencia; estado?: FiltroEstado; page: number }) {
  return useQuery({
    queryKey: [...comportamientosKey, 'lista', filtros],
    queryFn: () => listarComportamientos(filtros),
    placeholderData: keepPreviousData,
  })
}

/**
 * Detalle con los comportamientos por grado; `enabled` permite pedirlo solo cuando hace falta.
 * `siempreFresca` fuerza una lectura nueva al montar (la edición no debe partir de datos en caché).
 */
export function useComportamiento(competenciaId: number, enabled = true, siempreFresca = false) {
  return useQuery({
    queryKey: [...comportamientosKey, 'detalle', competenciaId],
    queryFn: () => obtenerComportamientos(competenciaId),
    enabled,
    refetchOnMount: siempreFresca ? 'always' : true,
  })
}

/** Competencias activas sin registro; siempre se leen de nuevo al abrir el formulario de creación. */
export function useCompetenciasDisponibles(enabled = true) {
  return useQuery({
    queryKey: [...comportamientosKey, 'disponibles'],
    queryFn: listarCompetenciasDisponibles,
    enabled,
    refetchOnMount: 'always',
  })
}
