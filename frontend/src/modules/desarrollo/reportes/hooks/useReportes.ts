import { keepPreviousData, useQuery } from '@tanstack/react-query'
import {
  listarDetalle,
  listarImpactos,
  listarSeguimiento,
  obtenerComentarios,
  obtenerDashboard,
  obtenerMiProgreso,
  obtenerOpciones,
  type FiltroEvaluado,
  type FiltrosReportes,
} from '../api'
import { reportesKey } from './claves'

export function useOpcionesReportes(enabled = true) {
  return useQuery({
    queryKey: [...reportesKey, 'opciones'],
    queryFn: obtenerOpciones,
    enabled,
  })
}

export function useDashboard(filtros: FiltrosReportes, enabled = true) {
  return useQuery({
    queryKey: [...reportesKey, 'dashboard', filtros],
    queryFn: () => obtenerDashboard(filtros),
    enabled,
    placeholderData: keepPreviousData,
  })
}

export function useDetalle(filtros: FiltrosReportes & { q?: string; page: number; per_page: number }, enabled = true) {
  return useQuery({
    queryKey: [...reportesKey, 'detalle', filtros],
    queryFn: () => listarDetalle(filtros),
    enabled,
    placeholderData: keepPreviousData,
  })
}

export function useComentarios(actividadId: number | null, page: number) {
  return useQuery({
    queryKey: [...reportesKey, 'comentarios', actividadId, page],
    queryFn: () => obtenerComentarios(actividadId as number, page),
    enabled: actividadId !== null,
    placeholderData: keepPreviousData,
    refetchOnMount: 'always',
  })
}

export function useSeguimiento(filtros: FiltrosReportes & { page: number; per_page: number }, enabled = true) {
  return useQuery({
    queryKey: [...reportesKey, 'seguimiento', filtros],
    queryFn: () => listarSeguimiento(filtros),
    enabled,
    placeholderData: keepPreviousData,
  })
}

export function useImpactos(filtros: { plan_id?: number; evaluado?: FiltroEvaluado; page: number; per_page: number }, enabled = true) {
  return useQuery({
    queryKey: [...reportesKey, 'impactos', filtros],
    queryFn: () => listarImpactos(filtros),
    enabled,
    placeholderData: keepPreviousData,
  })
}

export function useMiProgreso() {
  return useQuery({
    queryKey: [...reportesKey, 'mi-progreso'],
    queryFn: obtenerMiProgreso,
  })
}
