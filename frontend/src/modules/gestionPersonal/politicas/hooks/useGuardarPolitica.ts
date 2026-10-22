import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  crearBorrador,
  descartarBorrador,
  editarBorrador,
  previsualizarPublicacion,
  publicarVersion,
  retirarVersion,
  type CambiosDeBorrador,
  type DatosDePublicacion,
  type InicioDePublicacion,
  type TipoPolitica,
} from '../api'
import { politicaAplicableKey, politicasKey } from './usePoliticas'

/** Tras una escritura se invalida toda la familia: listado, detalle y condiciones aplicables. Las escrituras no se reintentan. */
function useInvalidar() {
  const queryClient = useQueryClient()
  return () => {
    void queryClient.invalidateQueries({ queryKey: politicasKey })
    void queryClient.invalidateQueries({ queryKey: politicaAplicableKey })
  }
}

export function useCrearBorrador(tipo: TipoPolitica) {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: (baseVersionId?: number) => crearBorrador(tipo, baseVersionId),
    retry: false,
    onSuccess: invalidar,
  })
}

export function useEditarBorrador(tipo: TipoPolitica) {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ id, cambios }: { id: number; cambios: CambiosDeBorrador }) => editarBorrador(tipo, id, cambios),
    retry: false,
    onSuccess: invalidar,
  })
}

export function useDescartarBorrador(tipo: TipoPolitica) {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ id, version }: { id: number; version: number }) => descartarBorrador(tipo, id, version),
    retry: false,
    onSuccess: invalidar,
  })
}

/** Resumen previo a publicar: no escribe nada, así que no invalida. */
export function usePrevisualizarPublicacion(tipo: TipoPolitica) {
  return useMutation({
    mutationFn: ({ id, inicio }: { id: number; inicio: InicioDePublicacion | null }) => previsualizarPublicacion(tipo, id, inicio),
    retry: false,
  })
}

export function usePublicarVersion(tipo: TipoPolitica) {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ id, datos, clave }: { id: number; datos: DatosDePublicacion; clave: string }) => publicarVersion(tipo, id, datos, clave),
    retry: false,
    onSuccess: invalidar,
  })
}

export function useRetirarVersion(tipo: TipoPolitica) {
  const invalidar = useInvalidar()
  return useMutation({
    mutationFn: ({ id, justificacion, clave }: { id: number; justificacion: string; clave: string }) => retirarVersion(tipo, id, justificacion, clave),
    retry: false,
    onSuccess: invalidar,
  })
}
