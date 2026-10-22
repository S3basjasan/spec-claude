import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { buscarColaboradores, obtenerOpcionesDeColaborador, obtenerPermisos, previsualizar, type ObjetivoElegido } from '../api'
import { adecuacionKey } from './claves'

/** Claves de permiso del usuario: deciden qué pestañas y botones se muestran (el backend sigue siendo la autoridad). */
export function usePermisosAdecuacion() {
  return useQuery({
    queryKey: [...adecuacionKey, 'permisos'],
    queryFn: obtenerPermisos,
    staleTime: 5 * 60 * 1000,
  })
}

export function useColaboradoresDeAdecuacion(q: string, enabled = true) {
  return useQuery({
    queryKey: [...adecuacionKey, 'opciones', 'colaboradores', q],
    queryFn: () => buscarColaboradores(q),
    enabled,
    placeholderData: keepPreviousData,
  })
}

/** Colaboradores, puestos, departamentos y rutas para los selectores y filtros de las pestañas. */
export function useOpcionesGenerales(enabled = true) {
  return useColaboradoresDeAdecuacion('', enabled)
}

/** Inscripciones, puestos e historial de un colaborador; siempre se leen de nuevo al elegirlo. */
export function useOpcionesDeColaborador(colaboradorId: number | null) {
  return useQuery({
    queryKey: [...adecuacionKey, 'opciones', 'colaborador', colaboradorId],
    queryFn: () => obtenerOpcionesDeColaborador(colaboradorId as number),
    enabled: colaboradorId !== null,
    refetchOnMount: 'always',
  })
}

/** Diagnóstico sin guardar: se recalcula en el servidor cada vez que cambia la selección. */
export function usePrevisualizacion(objetivo: ObjetivoElegido | null) {
  return useQuery({
    queryKey: [...adecuacionKey, 'previsualizar', objetivo],
    queryFn: () => previsualizar(objetivo as ObjetivoElegido),
    enabled: objetivo !== null,
    refetchOnMount: 'always',
    retry: false,
  })
}
