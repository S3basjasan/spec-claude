import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { listarHistorialMicro, listarMicros, obtenerMiAprendizaje, obtenerMicro, obtenerOpciones, type FiltrosMicro } from '../api'

export const microKey = ['micro'] as const

/** Página del repositorio del Administrador, con sus filtros. */
export function useRepositorio(filtros: FiltrosMicro) {
  return useQuery({
    queryKey: [...microKey, 'lista', filtros],
    queryFn: () => listarMicros(filtros),
    placeholderData: keepPreviousData,
  })
}

/** Detalle de un micro-contenido; `siempreFresco` fuerza una lectura nueva al montar (la edición no parte de la caché). */
export function useMicro(id: number | null, siempreFresco = false) {
  return useQuery({
    queryKey: [...microKey, 'detalle', id],
    queryFn: () => obtenerMicro(id as number),
    enabled: id !== null,
    refetchOnMount: siempreFresco ? 'always' : true,
  })
}

/** Competencias para el formulario (activas) y el filtro (todas). */
export function useOpcionesMicro(enabled = true) {
  return useQuery({
    queryKey: [...microKey, 'opciones'],
    queryFn: obtenerOpciones,
    enabled,
  })
}

export function useHistorialMicro(id: number, page: number) {
  return useQuery({
    queryKey: [...microKey, 'historial', id, page],
    queryFn: () => listarHistorialMicro(id, page),
    placeholderData: keepPreviousData,
  })
}

/** Resumen y contenidos del colaborador autenticado. */
export function useMiAprendizaje(enabled = true) {
  return useQuery({
    queryKey: [...microKey, 'mi-aprendizaje'],
    queryFn: obtenerMiAprendizaje,
    enabled,
  })
}
