import { keepPreviousData, useQuery } from '@tanstack/react-query'
import {
  buscarColaboradores,
  listarActuaciones,
  listarMisRespaldos,
  listarMisSolicitudes,
  listarRespaldosDeAdministrador,
  obtenerActuacion,
  obtenerContextoDeMovimiento,
  obtenerResumen,
  type FiltrosActuaciones,
} from '../api'

export const actuacionesKey = ['movimientos'] as const
export const misSolicitudesKey = ['mis-solicitudes'] as const

export function useListadoDeActuaciones(filtros: FiltrosActuaciones) {
  return useQuery({
    queryKey: [...actuacionesKey, 'listado', filtros],
    queryFn: ({ signal }) => listarActuaciones(filtros, signal),
    placeholderData: keepPreviousData,
  })
}

export function useResumenDeActuaciones() {
  return useQuery({ queryKey: [...actuacionesKey, 'resumen'], queryFn: ({ signal }) => obtenerResumen(signal) })
}

export function useActuacion(id: number) {
  return useQuery({
    queryKey: [...actuacionesKey, 'detalle', id],
    queryFn: ({ signal }) => obtenerActuacion(id, signal),
    enabled: Number.isInteger(id) && id > 0,
    // Las comprobaciones se calculan con datos de otros módulos: nunca se reutiliza una lectura vieja como vigente.
    staleTime: 0,
  })
}

export function useContextoDeMovimiento(colaboradorId: number | null, q: string) {
  return useQuery({
    queryKey: [...actuacionesKey, 'contexto', colaboradorId, q],
    queryFn: ({ signal }) => obtenerContextoDeMovimiento(colaboradorId as number, q, signal),
    enabled: colaboradorId !== null,
    staleTime: 0,
    gcTime: 0,
  })
}

export function useRespaldosDeAdministrador(colaboradorId: number | null) {
  return useQuery({
    queryKey: [...actuacionesKey, 'respaldos', colaboradorId],
    queryFn: ({ signal }) => listarRespaldosDeAdministrador(colaboradorId as number, signal),
    enabled: colaboradorId !== null,
  })
}

export function useBuscarColaboradores(q: string) {
  return useQuery({
    queryKey: [...actuacionesKey, 'colaboradores', q],
    queryFn: ({ signal }) => buscarColaboradores(q, signal),
    staleTime: 30_000,
  })
}

export function useMisSolicitudes() {
  return useQuery({ queryKey: [...misSolicitudesKey, 'listado'], queryFn: ({ signal }) => listarMisSolicitudes(signal) })
}

export function useMisRespaldos() {
  return useQuery({ queryKey: [...misSolicitudesKey, 'respaldos'], queryFn: ({ signal }) => listarMisRespaldos(signal) })
}
