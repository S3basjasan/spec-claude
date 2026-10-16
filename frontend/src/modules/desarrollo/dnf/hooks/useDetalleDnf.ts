import { useQuery } from '@tanstack/react-query'
import { obtenerDnf } from '../api'
import { dnfKey } from './claves'

/** Detalle de una DNF; con `colaboradorId` trae las brechas de ese colaborador en una masiva. */
export function useDetalleDnf(id: number | null, colaboradorId?: number | null) {
  return useQuery({
    queryKey: [...dnfKey, 'detalle', id, colaboradorId ?? null],
    queryFn: () => obtenerDnf(id as number, colaboradorId ?? undefined),
    enabled: id !== null,
  })
}
