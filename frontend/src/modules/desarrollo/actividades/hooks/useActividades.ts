import { keepPreviousData, useQuery } from '@tanstack/react-query'
import {
  listarAccionesDisponibles,
  listarActividades,
  listarCalificacionesPorActividad,
  listarCalificacionesPorParticipante,
  listarHistorial,
  obtenerActividad,
  obtenerDetalleParticipante,
  obtenerMisActividades,
  obtenerOpciones,
  type FiltrosActividades,
  type FiltrosCalificaciones,
} from '../api'
import { actividadesKey } from './claves'

type FiltrosAgrupados = Omit<FiltrosCalificaciones, 'agrupar' | 'colaborador_id'>

/** Página de la tabla del Administrador, con sus filtros. */
export function useActividades(filtros: FiltrosActividades) {
  return useQuery({
    queryKey: [...actividadesKey, 'lista', filtros],
    queryFn: () => listarActividades(filtros),
    placeholderData: keepPreviousData,
  })
}

/** Detalle con participantes; `siempreFresco` fuerza una lectura nueva al montar (el registro no parte de la caché). */
export function useActividad(id: number | null, siempreFresco = false) {
  return useQuery({
    queryKey: [...actividadesKey, 'detalle', id],
    queryFn: () => obtenerActividad(id as number),
    enabled: id !== null,
    refetchOnMount: siempreFresco ? 'always' : true,
  })
}

export function useAccionesDisponibles(filtros: { plan_id?: number; q?: string }, enabled = true) {
  return useQuery({
    queryKey: [...actividadesKey, 'acciones-disponibles', filtros],
    queryFn: () => listarAccionesDisponibles({ ...filtros, per_page: 50 }),
    enabled,
    refetchOnMount: 'always',
  })
}

/** Departamentos, competencias y planes para los filtros y el formulario. */
export function useOpcionesActividades(enabled = true) {
  return useQuery({
    queryKey: [...actividadesKey, 'opciones'],
    queryFn: obtenerOpciones,
    enabled,
  })
}

export function useHistorialActividad(id: number, page: number, enabled = true) {
  return useQuery({
    queryKey: [...actividadesKey, 'historial', id, page],
    queryFn: () => listarHistorial(id, page),
    enabled,
    placeholderData: keepPreviousData,
    refetchOnMount: 'always',
  })
}

export function useCalificacionesPorActividad(filtros: FiltrosAgrupados, enabled = true) {
  return useQuery({
    queryKey: [...actividadesKey, 'calificaciones', 'actividad', filtros],
    queryFn: () => listarCalificacionesPorActividad(filtros),
    enabled,
    placeholderData: keepPreviousData,
  })
}

export function useCalificacionesPorParticipante(filtros: FiltrosAgrupados, enabled = true) {
  return useQuery({
    queryKey: [...actividadesKey, 'calificaciones', 'participante', filtros],
    queryFn: () => listarCalificacionesPorParticipante(filtros),
    enabled,
    placeholderData: keepPreviousData,
  })
}

export function useDetalleParticipante(colaboradorId: number | null, filtros: Omit<FiltrosAgrupados, 'page' | 'per_page'>) {
  return useQuery({
    queryKey: [...actividadesKey, 'calificaciones', 'detalle', colaboradorId, filtros],
    queryFn: () => obtenerDetalleParticipante(colaboradorId as number, filtros),
    enabled: colaboradorId !== null,
  })
}

/** Actividades del colaborador autenticado (sin observaciones). */
export function useMisActividades(enabled = true) {
  return useQuery({
    queryKey: [...actividadesKey, 'mis-actividades'],
    queryFn: obtenerMisActividades,
    enabled,
  })
}
