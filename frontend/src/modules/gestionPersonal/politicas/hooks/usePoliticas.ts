import { useQuery } from '@tanstack/react-query'
import { toApiError } from '../../../../shared/api/httpClient'
import { listarPoliticas, obtenerPolitica, obtenerPoliticaAplicable, compararVersion, type FiltrosPoliticas, type TipoPolitica } from '../api'

export const politicasKey = ['politicas'] as const
export const politicaAplicableKey = ['politica-aplicable'] as const

/** Un 4xx es una respuesta definitiva del servidor (403, 404): no se reintenta; el resto, una vez. */
const sinReintentoEn4xx = (intentos: number, error: unknown) => {
  const { status } = toApiError(error)
  return (status === undefined || status >= 500) && intentos < 1
}

export function usePoliticas(filtros: FiltrosPoliticas) {
  return useQuery({
    queryKey: [...politicasKey, 'lista', filtros],
    queryFn: ({ signal }) => listarPoliticas(filtros, signal),
    retry: sinReintentoEn4xx,
  })
}

export function usePolitica(tipo: TipoPolitica | null) {
  return useQuery({
    queryKey: [...politicasKey, 'detalle', tipo],
    queryFn: ({ signal }) => obtenerPolitica(tipo as TipoPolitica, signal),
    enabled: tipo !== null,
    retry: sinReintentoEn4xx,
  })
}

/** Diferencias de una versión con la vigente real; solo se consulta cuando el panel de comparación está abierto. */
export function useComparacion(tipo: TipoPolitica, versionId: number | null) {
  return useQuery({
    queryKey: [...politicasKey, 'comparacion', tipo, versionId],
    queryFn: ({ signal }) => compararVersion(tipo, versionId as number, signal),
    enabled: versionId !== null,
    retry: sinReintentoEn4xx,
  })
}

/** Condiciones aplicables a un tipo para cualquier usuario autenticado; una falla nunca equivale a «sin requisitos». */
export function usePoliticaAplicable(tipo: TipoPolitica, habilitado = true) {
  return useQuery({
    queryKey: [...politicaAplicableKey, tipo],
    queryFn: ({ signal }) => obtenerPoliticaAplicable(tipo, signal),
    enabled: habilitado,
    retry: sinReintentoEn4xx,
  })
}
