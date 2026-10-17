import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { toApiError } from '../../../../shared/api/httpClient'
import { listarCategorias, listarCompetenciasDisponibles, listarCompetenciasFiltro, obtenerCategoria, type FiltrosCategorias } from '../api'

export const categoriasKey = ['categorias-reconocimiento'] as const

/** Un 4xx es una respuesta definitiva del servidor (403, 404): no se reintenta; el resto, una vez. */
const sinReintentoEn4xx = (intentos: number, error: unknown) => {
  const { status } = toApiError(error)
  return (status === undefined || status >= 500) && intentos < 1
}

/** Listado paginado; React Query cancela la petición anterior mediante `signal` al cambiar los filtros. */
export function useCategorias(filtros: FiltrosCategorias) {
  return useQuery({
    queryKey: [...categoriasKey, 'lista', filtros],
    queryFn: ({ signal }) => listarCategorias(filtros, signal),
    placeholderData: keepPreviousData,
    retry: sinReintentoEn4xx,
  })
}

/** Detalle con indicadores; sin `id` no consulta. */
export function useCategoria(id: number | null) {
  return useQuery({
    queryKey: [...categoriasKey, 'detalle', id],
    queryFn: ({ signal }) => obtenerCategoria(id as number, signal),
    enabled: id !== null,
    retry: sinReintentoEn4xx,
  })
}

/** Opciones del filtro de competencia (el Administrador recibe también las inactivas). */
export function useCompetenciasFiltro() {
  return useQuery({
    queryKey: [...categoriasKey, 'competencias-filtro'],
    queryFn: ({ signal }) => listarCompetenciasFiltro(signal),
    retry: sinReintentoEn4xx,
  })
}

/** Competencias activas del formulario (solo Administrador). */
export function useCompetenciasDisponibles(habilitado = true) {
  return useQuery({
    queryKey: [...categoriasKey, 'competencias-disponibles'],
    queryFn: ({ signal }) => listarCompetenciasDisponibles(signal),
    enabled: habilitado,
    retry: sinReintentoEn4xx,
  })
}
