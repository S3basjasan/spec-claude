import { keepPreviousData, useQuery } from '@tanstack/react-query'
import {
  buscarColaboradores,
  listarHistorial,
  listarInscripciones,
  listarRutas,
  obtenerMiRuta,
  obtenerOpcionesRutas,
  obtenerPermisos,
  obtenerRuta,
  type FiltrosInscripciones,
  type FiltrosRutas,
} from '../api'
import { rutasKey } from './claves'

/** Claves de permiso del usuario: deciden qué botones y secciones se muestran (el backend sigue siendo la autoridad). */
export function usePermisosRutas() {
  return useQuery({
    queryKey: [...rutasKey, 'permisos'],
    queryFn: obtenerPermisos,
    staleTime: 5 * 60 * 1000,
  })
}

export function useRutas(filtros: FiltrosRutas) {
  return useQuery({
    queryKey: [...rutasKey, 'lista', filtros],
    queryFn: () => listarRutas(filtros),
    placeholderData: keepPreviousData,
  })
}

/** Detalle con niveles, requisitos y cantera; siempre se lee de nuevo al abrir el editor. */
export function useRuta(id: number | null) {
  return useQuery({
    queryKey: [...rutasKey, 'detalle', id],
    queryFn: () => obtenerRuta(id as number),
    enabled: id !== null,
    refetchOnMount: 'always',
  })
}

export function useOpcionesRutas(enabled = true) {
  return useQuery({
    queryKey: [...rutasKey, 'opciones'],
    queryFn: obtenerOpcionesRutas,
    enabled,
  })
}

export function useHistorialRuta(id: number, page: number, enabled = true) {
  return useQuery({
    queryKey: [...rutasKey, 'historial', id, page],
    queryFn: () => listarHistorial(id, page),
    enabled,
    placeholderData: keepPreviousData,
  })
}

export function useInscripciones(filtros: FiltrosInscripciones, enabled = true) {
  return useQuery({
    queryKey: [...rutasKey, 'inscripciones', filtros],
    queryFn: () => listarInscripciones(filtros),
    enabled,
    placeholderData: keepPreviousData,
  })
}

export function useColaboradoresInscribibles(q: string, enabled = true) {
  return useQuery({
    queryKey: [...rutasKey, 'colaboradores', q],
    queryFn: () => buscarColaboradores(q),
    enabled,
  })
}

export function useMiRuta() {
  return useQuery({
    queryKey: [...rutasKey, 'mi-ruta'],
    queryFn: obtenerMiRuta,
  })
}
