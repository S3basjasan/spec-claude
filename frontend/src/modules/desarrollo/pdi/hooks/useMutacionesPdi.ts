import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  aceptarPdi,
  actualizarPdi,
  cambiarResponsable,
  cerrarPdi,
  confirmarAcuerdo,
  crearPdi,
  devolverABorrador,
  devolverDelColaborador,
  registrarAvanceDeAccion,
  registrarAvanceDeHito,
  vincularAccion,
  type CuerpoPdi,
  type EstadoAccion,
  type EstadoHito,
  type Pdi,
  type ResultadoCompromiso,
  type ResultadoDeCierre,
} from '../api'
import { pdiKey } from './claves'

/** Las escrituras nunca se reintentan automáticamente (Constitución II). */
const sinReintentos = { retry: false } as const

/** Tras cualquier cambio de un plan se refrescan su detalle, historial y las vistas que lo resumen. */
function useRefrescarPdi() {
  const queryClient = useQueryClient()

  return (plan: Pdi | null, id?: number) => {
    if (plan !== null) queryClient.setQueryData([...pdiKey, 'detalle', plan.id], plan)
    for (const parte of ['lista', 'gantt', 'sin-plan', 'mi-plan', 'historial', 'opciones']) {
      void queryClient.invalidateQueries({ queryKey: [...pdiKey, parte] })
    }
    if (plan === null && id !== undefined) void queryClient.invalidateQueries({ queryKey: [...pdiKey, 'detalle', id] })
  }
}

export function useCrearPdi() {
  const refrescar = useRefrescarPdi()

  return useMutation({ mutationFn: (cuerpo: CuerpoPdi): Promise<Pdi> => crearPdi(cuerpo), ...sinReintentos, onSuccess: (plan) => refrescar(plan) })
}

export function useActualizarPdi(id: number) {
  const refrescar = useRefrescarPdi()

  return useMutation({
    mutationFn: ({ cuerpo, version }: { cuerpo: CuerpoPdi; version: number }): Promise<Pdi> => actualizarPdi(id, cuerpo, version),
    ...sinReintentos,
    onSuccess: (plan) => refrescar(plan),
  })
}

export function useConfirmarAcuerdo(id: number) {
  const refrescar = useRefrescarPdi()

  return useMutation({ mutationFn: (version: number) => confirmarAcuerdo(id, version), ...sinReintentos, onSuccess: (plan) => refrescar(plan) })
}

export function useDevolverABorrador(id: number) {
  const refrescar = useRefrescarPdi()

  return useMutation({
    mutationFn: ({ motivo, version }: { motivo: string; version: number }) => devolverABorrador(id, motivo, version),
    ...sinReintentos,
    onSuccess: (plan) => refrescar(plan),
  })
}

export function useCambiarResponsable(id: number) {
  const refrescar = useRefrescarPdi()

  return useMutation({
    mutationFn: ({ responsableId, version }: { responsableId: number; version: number }) => cambiarResponsable(id, responsableId, version),
    ...sinReintentos,
    onSuccess: (plan) => refrescar(plan),
  })
}

export function useCerrarPdi(id: number) {
  const refrescar = useRefrescarPdi()

  return useMutation({
    mutationFn: ({ resultados, conclusion, version }: { resultados: { compromiso_id: number; resultado: ResultadoCompromiso }[]; conclusion: string | null; version: number }): Promise<ResultadoDeCierre> =>
      cerrarPdi(id, resultados, conclusion, version),
    ...sinReintentos,
    onSuccess: (cierre) => refrescar(cierre.plan),
  })
}

export function useAvanceDeAccion() {
  const refrescar = useRefrescarPdi()

  return useMutation({
    mutationFn: ({ accionId, estado }: { accionId: number; estado: EstadoAccion }) => registrarAvanceDeAccion(accionId, estado),
    ...sinReintentos,
    onSuccess: (plan) => refrescar(plan),
  })
}

export function useAvanceDeHito() {
  const refrescar = useRefrescarPdi()

  return useMutation({
    mutationFn: ({ hitoId, estado, observaciones }: { hitoId: number; estado: EstadoHito; observaciones: string | null }) => registrarAvanceDeHito(hitoId, estado, observaciones),
    ...sinReintentos,
    onSuccess: (plan) => refrescar(plan),
  })
}

export function useVincularAccion() {
  const refrescar = useRefrescarPdi()

  return useMutation({
    mutationFn: ({ accionId, planAccionId, version }: { accionId: number; planAccionId: number | null; version: number | null }) => vincularAccion(accionId, planAccionId, version),
    ...sinReintentos,
    onSuccess: (plan) => refrescar(plan),
  })
}

/** El colaborador acepta su plan. */
export function useAceptarPdi(id: number) {
  const refrescar = useRefrescarPdi()

  return useMutation({
    mutationFn: (version: number): Promise<Pdi> => aceptarPdi(id, version),
    ...sinReintentos,
    onSuccess: (plan) => refrescar(plan),
  })
}

/** El colaborador devuelve su plan con un comentario; el plan deja de ser visible para él. */
export function useDevolverDelColaborador(id: number) {
  const refrescar = useRefrescarPdi()

  return useMutation({
    mutationFn: ({ comentario, version }: { comentario: string; version: number }) => devolverDelColaborador(id, comentario, version),
    ...sinReintentos,
    onSuccess: () => refrescar(null, id),
  })
}
