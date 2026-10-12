import { keepPreviousData, useQuery } from '@tanstack/react-query'
import {
  listarCompetenciasDisponibles,
  listarPreguntas,
  obtenerPreguntas,
  type FiltroEstado,
  type TipoCompetencia,
} from '../api'

export const preguntasKey = ['preguntas'] as const

export function usePreguntas(filtros: { q: string; tipo?: TipoCompetencia; estado?: FiltroEstado; page: number }) {
  return useQuery({
    queryKey: [...preguntasKey, 'lista', filtros],
    queryFn: () => listarPreguntas(filtros),
    placeholderData: keepPreviousData,
  })
}

/**
 * Detalle con las cuatro preguntas; `enabled` permite pedirlo solo cuando hace falta.
 * `siempreFresca` fuerza una lectura nueva al montar (la edición no debe partir de datos en caché).
 */
export function usePregunta(competenciaId: number, enabled = true, siempreFresca = false) {
  return useQuery({
    queryKey: [...preguntasKey, 'detalle', competenciaId],
    queryFn: () => obtenerPreguntas(competenciaId),
    enabled,
    refetchOnMount: siempreFresca ? 'always' : true,
  })
}

/** Competencias activas sin registro; siempre se leen de nuevo al abrir el formulario de creación. */
export function useCompetenciasDisponibles(enabled = true) {
  return useQuery({
    queryKey: [...preguntasKey, 'disponibles'],
    queryFn: listarCompetenciasDisponibles,
    enabled,
    refetchOnMount: 'always',
  })
}
