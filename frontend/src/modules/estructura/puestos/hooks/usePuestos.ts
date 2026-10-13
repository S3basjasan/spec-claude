import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { listarCompetenciasDisponibles, listarPuestos, obtenerPuesto, type FiltroEstado } from '../api'

export const puestosKey = ['puestos'] as const

export function usePuestos(filtros: { q: string; estado?: FiltroEstado; page: number }) {
  return useQuery({
    queryKey: [...puestosKey, 'lista', filtros],
    queryFn: () => listarPuestos(filtros),
    placeholderData: keepPreviousData,
  })
}

/**
 * Detalle con funciones, requisitos y competencias; `enabled` permite pedirlo solo cuando hace falta.
 * `siempreFresco` fuerza una lectura nueva al montar (la edición no debe partir de datos en caché).
 */
export function usePuesto(id: number, enabled = true, siempreFresco = false) {
  return useQuery({
    queryKey: [...puestosKey, 'detalle', id],
    queryFn: () => obtenerPuesto(id),
    enabled,
    refetchOnMount: siempreFresco ? 'always' : true,
  })
}

/** Competencias activas con sus grados; siempre se leen de nuevo al abrir el formulario. */
export function useCompetenciasDisponibles(enabled = true) {
  return useQuery({
    queryKey: [...puestosKey, 'competencias-disponibles'],
    queryFn: listarCompetenciasDisponibles,
    enabled,
    refetchOnMount: 'always',
  })
}
