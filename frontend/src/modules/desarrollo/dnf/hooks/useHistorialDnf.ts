import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { listarDnf, type FiltrosHistorial } from '../api'
import { dnfKey } from './claves'

/** Página del historial de DNF con sus filtros. */
export function useHistorialDnf(filtros: FiltrosHistorial) {
  return useQuery({
    queryKey: [...dnfKey, 'lista', filtros],
    queryFn: () => listarDnf(filtros),
    placeholderData: keepPreviousData,
  })
}
