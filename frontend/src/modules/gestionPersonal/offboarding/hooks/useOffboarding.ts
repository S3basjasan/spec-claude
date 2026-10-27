import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  buscarColaboradores,
  completarTarea,
  consultarRespuestas,
  ejecutarAccion,
  formalizarSalida,
  guardarMiEntrevista,
  listarRespaldos,
  listarSalidas,
  obtenerAgregados,
  obtenerContextoDeSalida,
  obtenerMiDesvinculacion,
  obtenerMiEntrevista,
  obtenerResumen,
  obtenerSalida,
  resolverMiEntrevista,
  type AccionDeSalida,
  type CuerpoDeAccion,
  type DatosDeSalida,
  type FiltrosDeSalidas,
  type RespuestasDeEntrevista,
} from '../api'

export const salidasKey = ['offboarding'] as const
export const miDesvinculacionKey = ['mi-desvinculacion'] as const

export function useListadoDeSalidas(filtros: FiltrosDeSalidas) {
  return useQuery({
    queryKey: [...salidasKey, 'listado', filtros],
    queryFn: ({ signal }) => listarSalidas(filtros, signal),
    placeholderData: keepPreviousData,
  })
}

export function useResumenDeSalidas() {
  return useQuery({ queryKey: [...salidasKey, 'resumen'], queryFn: ({ signal }) => obtenerResumen(signal) })
}

export function useSalida(id: number) {
  return useQuery({
    queryKey: [...salidasKey, 'detalle', id],
    queryFn: ({ signal }) => obtenerSalida(id, signal),
    enabled: Number.isInteger(id) && id > 0,
    // El estado de aplicación y de cierre depende de la fecha y de otros módulos: nunca se reutiliza una lectura vieja.
    staleTime: 0,
  })
}

export function useContextoDeSalida(colaboradorId: number | null) {
  return useQuery({
    queryKey: [...salidasKey, 'contexto', colaboradorId],
    queryFn: ({ signal }) => obtenerContextoDeSalida(colaboradorId as number, signal),
    enabled: colaboradorId !== null,
    staleTime: 0,
    gcTime: 0,
  })
}

export function useRespaldosDeSalida(colaboradorId: number | null) {
  return useQuery({
    queryKey: [...salidasKey, 'respaldos', colaboradorId],
    queryFn: ({ signal }) => listarRespaldos(colaboradorId as number, signal),
    enabled: colaboradorId !== null,
  })
}

export function useBuscarColaboradores(q: string) {
  return useQuery({
    queryKey: [...salidasKey, 'colaboradores', q],
    queryFn: ({ signal }) => buscarColaboradores(q, signal),
    staleTime: 30_000,
  })
}

/** Respuestas originales: solo se piden cuando el Administrador las abre (cada consulta queda registrada). */
export function useRespuestasConsultadas(id: number, habilitado: boolean) {
  return useQuery({
    queryKey: [...salidasKey, 'respuestas', id],
    queryFn: ({ signal }) => consultarRespuestas(id, signal),
    enabled: habilitado,
    staleTime: 0,
    gcTime: 0,
    retry: false,
  })
}

export function useAgregados() {
  return useQuery({ queryKey: [...salidasKey, 'agregados'], queryFn: ({ signal }) => obtenerAgregados(signal) })
}

/** Tras una escritura se invalida la familia correspondiente. Las escrituras no se reintentan automáticamente. */
function useInvalidar() {
  const queryClient = useQueryClient()
  return () => {
    void queryClient.invalidateQueries({ queryKey: salidasKey })
    void queryClient.invalidateQueries({ queryKey: miDesvinculacionKey })
  }
}

export function useFormalizarSalida() {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ datos, clave }: { datos: DatosDeSalida; clave: string }) => formalizarSalida(datos, clave),
    retry: false,
    onSuccess: invalidar,
  })
}

export function useAccionDeSalida(id: number) {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ accion, cuerpo, clave }: { accion: AccionDeSalida; cuerpo: CuerpoDeAccion; clave: string }) => ejecutarAccion(id, accion, cuerpo, clave),
    retry: false,
    onSettled: invalidar,
  })
}

export function useCompletarTarea(id: number) {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ tareaId, version, documentoIds, clave }: { tareaId: number; version: number; documentoIds: number[]; clave: string }) =>
      completarTarea(id, tareaId, { version, documento_ids: documentoIds }, clave),
    retry: false,
    onSettled: invalidar,
  })
}

// ------------------------------------------------------------------ Colaborador

export function useMiDesvinculacion() {
  return useQuery({ queryKey: [...miDesvinculacionKey, 'resumen'], queryFn: ({ signal }) => obtenerMiDesvinculacion(signal), retry: false, staleTime: 0 })
}

export function useMiEntrevista() {
  return useQuery({ queryKey: [...miDesvinculacionKey, 'entrevista'], queryFn: ({ signal }) => obtenerMiEntrevista(signal), retry: false, staleTime: 0 })
}

export function useGuardarMiEntrevista() {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ version, respuestas, clave }: { version: number; respuestas: RespuestasDeEntrevista; clave: string }) => guardarMiEntrevista({ version, ...respuestas }, clave),
    retry: false,
    onSuccess: invalidar,
  })
}

export function useResolverMiEntrevista() {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ accion, version, clave }: { accion: 'confirmacion' | 'declinacion'; version: number; clave: string }) => resolverMiEntrevista(accion, version, clave),
    retry: false,
    onSettled: invalidar,
  })
}
