import { keepPreviousData, useQuery } from '@tanstack/react-query'
import {
  listarAreas,
  listarCatalogo,
  listarCatalogoCompleto,
  listarHistorial,
  listarRecomendados,
  listarRecursosDisponibles,
  obtenerCobertura,
  obtenerDetalle,
  type FiltrosCatalogo,
  type TipoRecurso,
} from '../api'

export const catalogoKey = ['catalogo'] as const

/** Página de la matriz del Administrador, con sus filtros. */
export function useCatalogo(filtros: FiltrosCatalogo) {
  return useQuery({
    queryKey: [...catalogoKey, 'lista', filtros],
    queryFn: () => listarCatalogo(filtros),
    placeholderData: keepPreviousData,
  })
}

/** Todas las competencias visibles para el Colaborador (se agrupan por área, sin paginar). */
export function useCatalogoCompleto() {
  return useQuery({
    queryKey: [...catalogoKey, 'completo'],
    queryFn: listarCatalogoCompleto,
  })
}

/** Detalle formativo; `siempreFresco` fuerza una lectura nueva al montar (la edición no parte de la caché). */
export function useDetalleFormativo(id: number | null, siempreFresco = false) {
  return useQuery({
    queryKey: [...catalogoKey, 'detalle', id],
    queryFn: () => obtenerDetalle(id as number),
    enabled: id !== null,
    refetchOnMount: siempreFresco ? 'always' : true,
  })
}

export function useCobertura(enabled = true) {
  return useQuery({
    queryKey: [...catalogoKey, 'cobertura'],
    queryFn: obtenerCobertura,
    enabled,
  })
}

export function useAreas(enabled = true) {
  return useQuery({
    queryKey: [...catalogoKey, 'areas'],
    queryFn: listarAreas,
    enabled,
  })
}

/** Recomendados del Colaborador; si falla o viene vacío, la sección simplemente no se muestra. */
export function useRecomendados(enabled = true) {
  return useQuery({
    queryKey: [...catalogoKey, 'recomendados'],
    queryFn: listarRecomendados,
    enabled,
    retry: false,
  })
}

/** Recursos disponibles para asociar a una competencia (se leen de nuevo al abrir el selector). */
export function useRecursosDisponibles(tipo: TipoRecurso | null, competenciaId: number, q: string) {
  return useQuery({
    queryKey: [...catalogoKey, 'recursos', tipo, competenciaId, q],
    queryFn: () => listarRecursosDisponibles(tipo as TipoRecurso, competenciaId, q),
    enabled: tipo !== null,
    refetchOnMount: 'always',
  })
}

export function useHistorial(id: number, page: number, enabled = true) {
  return useQuery({
    queryKey: [...catalogoKey, 'historial', id, page],
    queryFn: () => listarHistorial(id, page),
    enabled,
    placeholderData: keepPreviousData,
  })
}
