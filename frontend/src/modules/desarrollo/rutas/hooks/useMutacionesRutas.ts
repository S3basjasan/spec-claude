import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  actualizarRuta,
  alcanzarNivel,
  cambiarEstadoRuta,
  cambiarNiveles,
  crearRuta,
  inscribir,
  retirarInscripcion,
  type CuerpoActualizarRuta,
  type DatosInscripcion,
  type DatosRuta,
  type EstadoRuta,
  type Inscripcion,
  type RutaDetalle,
} from '../api'
import { rutasKey } from './claves'

/** Las escrituras nunca se reintentan automáticamente (Constitución II). */
const sinReintentos = { retry: false } as const

export function useCrearRuta() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosRuta): Promise<RutaDetalle> => crearRuta(datos),
    ...sinReintentos,
    onSuccess: (ruta) => {
      queryClient.setQueryData([...rutasKey, 'detalle', ruta.id], ruta)
      void queryClient.invalidateQueries({ queryKey: [...rutasKey, 'lista'] })
    },
  })
}

/** Reemplaza los datos y los niveles de la ruta (con su versión); se usa también para agregar, reordenar y quitar niveles. */
export function useActualizarRuta(id: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (cuerpo: CuerpoActualizarRuta): Promise<RutaDetalle> => actualizarRuta(id, cuerpo),
    ...sinReintentos,
    onSuccess: (ruta) => {
      queryClient.setQueryData([...rutasKey, 'detalle', id], ruta)
      void queryClient.invalidateQueries({ queryKey: [...rutasKey, 'lista'] })
      void queryClient.invalidateQueries({ queryKey: [...rutasKey, 'historial', id] })
    },
  })
}

export function useCambiarEstadoRuta(id: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ version, estado, confirmarInscritos }: { version: number; estado: Exclude<EstadoRuta, 'Borrador'>; confirmarInscritos?: boolean }): Promise<RutaDetalle> =>
      cambiarEstadoRuta(id, version, estado, confirmarInscritos ?? false),
    ...sinReintentos,
    onSuccess: (ruta) => {
      queryClient.setQueryData([...rutasKey, 'detalle', id], ruta)
      void queryClient.invalidateQueries({ queryKey: [...rutasKey, 'lista'] })
      void queryClient.invalidateQueries({ queryKey: [...rutasKey, 'historial', id] })
    },
  })
}

/** Tras cambiar una inscripción se refrescan la ruta (cantera), la lista, las inscripciones, el historial y «Mi ruta». */
function useRefrescarTrasInscripcion(rutaId: number) {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: [...rutasKey, 'detalle', rutaId] })
    void queryClient.invalidateQueries({ queryKey: [...rutasKey, 'inscripciones'] })
    void queryClient.invalidateQueries({ queryKey: [...rutasKey, 'lista'] })
    void queryClient.invalidateQueries({ queryKey: [...rutasKey, 'historial', rutaId] })
    void queryClient.invalidateQueries({ queryKey: [...rutasKey, 'mi-ruta'] })
  }
}

export function useInscribir(rutaId: number) {
  const refrescar = useRefrescarTrasInscripcion(rutaId)

  return useMutation({
    mutationFn: (datos: DatosInscripcion): Promise<Inscripcion> => inscribir(datos),
    ...sinReintentos,
    onSuccess: refrescar,
  })
}

export function useCambiarNiveles(rutaId: number) {
  const refrescar = useRefrescarTrasInscripcion(rutaId)

  return useMutation({
    mutationFn: ({ id, actual, objetivo }: { id: number; actual: number | null; objetivo: number | null }): Promise<Inscripcion> => cambiarNiveles(id, actual, objetivo),
    ...sinReintentos,
    onSuccess: refrescar,
  })
}

export function useAlcanzarNivel(rutaId: number) {
  const refrescar = useRefrescarTrasInscripcion(rutaId)

  return useMutation({
    mutationFn: (id: number): Promise<{ inscripcion: Inscripcion; nota: string }> => alcanzarNivel(id),
    ...sinReintentos,
    onSuccess: refrescar,
  })
}

export function useRetirarInscripcion(rutaId: number) {
  const refrescar = useRefrescarTrasInscripcion(rutaId)

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }): Promise<Inscripcion> => retirarInscripcion(id, motivo),
    ...sinReintentos,
    onSuccess: refrescar,
  })
}
