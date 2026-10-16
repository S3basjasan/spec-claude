import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { toApiError } from '../../../../shared/api/httpClient'
import { listarColaboradores, obtenerColaborador, obtenerFiltros, obtenerMiFicha, type FiltrosListado } from '../api'

export const colaboradoresKey = ['colaboradores'] as const
export const miFichaKey = ['mi-ficha'] as const

/** Un 4xx es una respuesta definitiva del servidor (404, 403): no se reintenta; el resto, una vez. */
const sinReintentoEn4xx = (intentos: number, error: unknown) => {
  const { status } = toApiError(error)
  return (status === undefined || status >= 500) && intentos < 1
}

/** Listado paginado; React Query cancela la petición anterior mediante `signal` al cambiar los filtros. */
export function useColaboradores(filtros: FiltrosListado) {
  return useQuery({
    queryKey: [...colaboradoresKey, 'lista', filtros],
    queryFn: ({ signal }) => listarColaboradores(filtros, signal),
    placeholderData: keepPreviousData,
  })
}

/** Opciones de los filtros: «Todos» y los estados presentes, más las unidades publicadas. */
export function useFiltrosDeColaboradores(enabled = true) {
  return useQuery({
    queryKey: [...colaboradoresKey, 'filtros'],
    queryFn: obtenerFiltros,
    enabled,
  })
}

/** Detalle de una ficha; `siempreFresco` fuerza una lectura nueva al abrir la edición. */
export function useColaborador(id: number, siempreFresco = false) {
  return useQuery({
    queryKey: [...colaboradoresKey, 'detalle', id],
    queryFn: () => obtenerColaborador(id),
    refetchOnMount: siempreFresco ? 'always' : true,
    retry: sinReintentoEn4xx,
  })
}

/** Ficha propia de la cuenta autenticada. */
export function useMiFicha() {
  return useQuery({
    queryKey: miFichaKey,
    queryFn: obtenerMiFicha,
    retry: sinReintentoEn4xx,
  })
}
