import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { listarUnidades, obtenerImpacto, obtenerOpciones, obtenerUnidad, type FiltroEstado } from '../api'

export const unidadesKey = ['unidades'] as const

export function useUnidades(filtros: { q: string; estado?: FiltroEstado }) {
  return useQuery({
    queryKey: [...unidadesKey, 'lista', filtros],
    queryFn: () => listarUnidades(filtros),
    placeholderData: keepPreviousData,
  })
}

/**
 * Detalle con fechas, dependientes y (solo Administrador) auditoría; `enabled` permite pedirlo solo cuando hace falta.
 * `siempreFresca` fuerza una lectura nueva al montar (la edición no debe partir de datos en caché).
 */
export function useUnidad(id: number, enabled = true, siempreFresca = false) {
  return useQuery({
    queryKey: [...unidadesKey, 'detalle', id],
    queryFn: () => obtenerUnidad(id),
    enabled,
    refetchOnMount: siempreFresca ? 'always' : true,
  })
}

/** Cadena de tipos y unidades candidatas a padre; `reubicar` es la unidad que se edita. Siempre se leen de nuevo al abrir. */
export function useOpcionesDeUnidad(reubicar?: number, enabled = true) {
  return useQuery({
    queryKey: [...unidadesKey, 'opciones', reubicar ?? null],
    queryFn: () => obtenerOpciones(reubicar),
    enabled,
    refetchOnMount: 'always',
  })
}

/** Impacto de desactivar una unidad: lista de dependientes y posiciones vigentes. Siempre fresco: lo decide el servidor. */
export function useImpactoDeDesactivacion(id: number, enabled = true) {
  return useQuery({
    queryKey: [...unidadesKey, 'impacto', id],
    queryFn: () => obtenerImpacto(id),
    enabled,
    refetchOnMount: 'always',
    gcTime: 0,
  })
}
