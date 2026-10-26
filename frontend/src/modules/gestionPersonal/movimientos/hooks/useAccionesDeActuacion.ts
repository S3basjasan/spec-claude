import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  ejecutarAccion,
  informarAusencia,
  presentarMiNovedad,
  presentarNovedadComoAdministrador,
  proponerMovimiento,
  retirarMiNovedad,
  type AccionDeActuacion,
  type CuerpoDeAccion,
  type DatosDeAusencia,
  type DatosDeMovimiento,
  type DatosDeNovedad,
} from '../api'
import { actuacionesKey, misSolicitudesKey } from './useActuaciones'

/** Tras una escritura se invalida la familia correspondiente. Las escrituras no se reintentan automáticamente. */
function useInvalidar() {
  const queryClient = useQueryClient()
  return () => {
    void queryClient.invalidateQueries({ queryKey: actuacionesKey })
    void queryClient.invalidateQueries({ queryKey: misSolicitudesKey })
  }
}

export function usePresentarNovedadAdministrador() {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ colaboradorId, datos, clave }: { colaboradorId: number; datos: DatosDeNovedad; clave: string }) =>
      presentarNovedadComoAdministrador(colaboradorId, datos, clave),
    retry: false,
    onSuccess: invalidar,
  })
}

export function useInformarAusencia() {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ colaboradorId, datos, clave }: { colaboradorId: number; datos: DatosDeAusencia; clave: string }) =>
      informarAusencia(colaboradorId, datos, clave),
    retry: false,
    onSuccess: invalidar,
  })
}

export function useProponerMovimiento() {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ colaboradorId, datos, clave }: { colaboradorId: number; datos: DatosDeMovimiento; clave: string }) =>
      proponerMovimiento(colaboradorId, datos, clave),
    retry: false,
    onSuccess: invalidar,
  })
}

export function useAccionDeActuacion(id: number) {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ accion, cuerpo, clave }: { accion: AccionDeActuacion; cuerpo: CuerpoDeAccion; clave: string }) =>
      ejecutarAccion(id, accion, cuerpo, clave),
    retry: false,
    onSettled: invalidar,
  })
}

export function usePresentarMiNovedad() {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ datos, clave }: { datos: DatosDeNovedad; clave: string }) => presentarMiNovedad(datos, clave),
    retry: false,
    onSuccess: invalidar,
  })
}

export function useRetirarMiNovedad() {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ id, version, clave }: { id: number; version: number; clave: string }) => retirarMiNovedad(id, version, clave),
    retry: false,
    onSettled: invalidar,
  })
}
