import { keepPreviousData, useQuery } from '@tanstack/react-query'
import {
  listarAccionesVinculables,
  listarHistorial,
  listarPdi,
  listarSinPlan,
  obtenerGantt,
  obtenerMiPlan,
  obtenerOpciones,
  obtenerOpcionesDeColaborador,
  obtenerPdi,
  obtenerPermisos,
  obtenerPrecarga,
  type FiltrosGantt,
  type FiltrosLista,
  type TipoPdi,
} from '../api'
import { pdiKey } from './claves'

/** Claves de permiso del usuario: deciden qué pestañas y botones se muestran (el backend sigue siendo la autoridad). */
export function usePermisosPdi() {
  return useQuery({ queryKey: [...pdiKey, 'permisos'], queryFn: obtenerPermisos, staleTime: 5 * 60 * 1000 })
}

export function useOpciones(q: string, enabled = true) {
  return useQuery({ queryKey: [...pdiKey, 'opciones', q], queryFn: () => obtenerOpciones(q), enabled, placeholderData: keepPreviousData })
}

/** Puesto objetivo sugerido y planes abiertos del colaborador; se lee de nuevo al elegirlo. */
export function useOpcionesDeColaborador(colaboradorId: number | null) {
  return useQuery({
    queryKey: [...pdiKey, 'opciones', 'colaborador', colaboradorId],
    queryFn: () => obtenerOpcionesDeColaborador(colaboradorId as number),
    enabled: colaboradorId !== null,
    refetchOnMount: 'always',
  })
}

/** Compromisos sugeridos desde la última DNF o adecuación (no guarda nada). */
export function usePrecarga(colaboradorId: number | null, tipo: TipoPdi, puestoObjetivoId: number | null, enabled: boolean) {
  const lista = enabled && colaboradorId !== null && (tipo === 'Mejora' || puestoObjetivoId !== null)

  return useQuery({
    queryKey: [...pdiKey, 'precarga', colaboradorId, tipo, puestoObjetivoId],
    queryFn: () => obtenerPrecarga(colaboradorId as number, tipo, puestoObjetivoId),
    enabled: lista,
    refetchOnMount: 'always',
  })
}

/** Detalle de un plan; siempre se lee de nuevo al abrirlo. */
export function usePdi(id: number | null) {
  return useQuery({
    queryKey: [...pdiKey, 'detalle', id],
    queryFn: () => obtenerPdi(id as number),
    enabled: id !== null,
    refetchOnMount: 'always',
  })
}

export function useListaPdi(filtros: FiltrosLista, enabled = true) {
  return useQuery({ queryKey: [...pdiKey, 'lista', filtros], queryFn: () => listarPdi(filtros), enabled, placeholderData: keepPreviousData })
}

export function useHistorialPdi(id: number | null, page: number) {
  return useQuery({
    queryKey: [...pdiKey, 'historial', id, page],
    queryFn: () => listarHistorial(id as number, page),
    enabled: id !== null,
    placeholderData: keepPreviousData,
  })
}

export function useGantt(filtros: FiltrosGantt, enabled = true) {
  return useQuery({ queryKey: [...pdiKey, 'gantt', filtros], queryFn: () => obtenerGantt(filtros), enabled, placeholderData: keepPreviousData })
}

export function useSinPlan(departamentoId: number | undefined, page: number, enabled = true) {
  return useQuery({
    queryKey: [...pdiKey, 'sin-plan', departamentoId ?? null, page],
    queryFn: () => listarSinPlan(departamentoId, page),
    enabled,
    placeholderData: keepPreviousData,
  })
}

export function useMiPlan(enabled = true) {
  return useQuery({ queryKey: [...pdiKey, 'mi-plan'], queryFn: obtenerMiPlan, enabled, refetchOnMount: 'always' })
}

export function useAccionesVinculables(colaboradorId: number | null, q: string, enabled: boolean) {
  return useQuery({
    queryKey: [...pdiKey, 'acciones-vinculables', colaboradorId, q],
    queryFn: () => listarAccionesVinculables(colaboradorId as number, q),
    enabled: enabled && colaboradorId !== null,
    refetchOnMount: 'always',
  })
}
