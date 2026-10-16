import { keepPreviousData, useQuery } from '@tanstack/react-query'
import {
  listarPosiciones,
  listarUnidadesParaFiltro,
  obtenerOpciones,
  obtenerPosicion,
  type FiltroEstado,
} from '../api'

export const posicionesKey = ['posiciones'] as const

export function usePosiciones(filtros: { q: string; estado?: FiltroEstado; unidad_organizacional_id?: number; page: number }) {
  return useQuery({
    queryKey: [...posicionesKey, 'lista', filtros],
    queryFn: () => listarPosiciones(filtros),
    placeholderData: keepPreviousData,
  })
}

/**
 * Detalle con ocupante, supervisor, subordinadas y (solo Administrador) auditoría; `enabled` permite pedirlo solo cuando
 * hace falta. `siempreFresca` fuerza una lectura nueva al montar (la edición no debe partir de datos en caché).
 */
export function usePosicion(id: number, enabled = true, siempreFresca = false) {
  return useQuery({
    queryKey: [...posicionesKey, 'detalle', id],
    queryFn: () => obtenerPosicion(id),
    enabled,
    refetchOnMount: siempreFresca ? 'always' : true,
  })
}

/** Puestos activos, unidades y supervisores candidatos; `excluir` es la posición que se edita. Siempre se leen de nuevo al abrir. */
export function useOpcionesDePosicion(excluir?: number, enabled = true) {
  return useQuery({
    queryKey: [...posicionesKey, 'opciones', excluir ?? null],
    queryFn: () => obtenerOpciones(excluir),
    enabled,
    refetchOnMount: 'always',
  })
}

/** Unidades en orden de árbol para el filtro del listado. */
export function useUnidadesParaFiltro() {
  return useQuery({
    queryKey: [...posicionesKey, 'unidades-filtro'],
    queryFn: listarUnidadesParaFiltro,
  })
}
