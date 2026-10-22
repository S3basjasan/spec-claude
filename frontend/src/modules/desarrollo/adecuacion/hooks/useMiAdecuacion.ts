import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { obtenerMiAdecuacion } from '../api'
import { adecuacionKey } from './claves'

/** Las adecuaciones vigentes del usuario (una por puesto objetivo); la identidad sale de la sesión, no de un parámetro. */
export function useMiAdecuacion(puestoObjetivoId: number | undefined) {
  return useQuery({
    queryKey: [...adecuacionKey, 'mia', puestoObjetivoId ?? null],
    queryFn: () => obtenerMiAdecuacion(puestoObjetivoId),
    placeholderData: keepPreviousData,
  })
}
