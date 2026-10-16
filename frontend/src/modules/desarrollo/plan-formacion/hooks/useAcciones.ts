import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { listarAcciones, type FiltrosAcciones } from '../api'
import { accionesKey } from './claves'

/** Acciones de todos los planes (Lista y Calendario); `habilitado=false` evita la consulta cuando el rango es vacío. */
export function useAcciones(filtros: FiltrosAcciones, habilitado = true) {
  return useQuery({
    queryKey: [...accionesKey, 'lista', filtros],
    queryFn: () => listarAcciones(filtros),
    enabled: habilitado,
    placeholderData: keepPreviousData,
  })
}
