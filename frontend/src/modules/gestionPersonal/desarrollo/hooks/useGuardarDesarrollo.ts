import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  actualizarMovilidad,
  corregirExperiencia,
  corregirFormacion,
  crearExperiencia,
  crearFormacion,
  type DatosExperiencia,
  type DatosFormacion,
  type DatosMovilidad,
} from '../api'
import { desarrolloKey } from './useDesarrollo'

/** Tras guardar se vuelve a consultar el desarrollo; las escrituras nunca se reintentan automáticamente. */
function useInvalidarDesarrollo(colaboradorId: number) {
  const queryClient = useQueryClient()
  return () => void queryClient.invalidateQueries({ queryKey: desarrolloKey(colaboradorId) })
}

interface GuardarFormacion {
  datos: DatosFormacion
  /** Sin `id` ni `version`, crea; con ambos, corrige esa fila. */
  fila?: { id: number; version: number }
}

export function useGuardarFormacion(colaboradorId: number) {
  const invalidar = useInvalidarDesarrollo(colaboradorId)
  return useMutation({
    mutationFn: ({ datos, fila }: GuardarFormacion) =>
      fila ? corregirFormacion(colaboradorId, fila.id, datos, fila.version) : crearFormacion(colaboradorId, datos),
    retry: false,
    onSuccess: invalidar,
  })
}

interface GuardarExperiencia {
  datos: DatosExperiencia
  fila?: { id: number; version: number }
}

export function useGuardarExperiencia(colaboradorId: number) {
  const invalidar = useInvalidarDesarrollo(colaboradorId)
  return useMutation({
    mutationFn: ({ datos, fila }: GuardarExperiencia) =>
      fila ? corregirExperiencia(colaboradorId, fila.id, datos, fila.version) : crearExperiencia(colaboradorId, datos),
    retry: false,
    onSuccess: invalidar,
  })
}

export function useGuardarMovilidad(colaboradorId: number) {
  const invalidar = useInvalidarDesarrollo(colaboradorId)
  return useMutation({
    mutationFn: ({ datos, version }: { datos: DatosMovilidad; version: number }) => actualizarMovilidad(colaboradorId, datos, version),
    retry: false,
    onSuccess: invalidar,
  })
}
