import { useQuery } from '@tanstack/react-query'
import { listarEscalas } from '../api'

/** Catálogo de escalas: es pequeño y casi no cambia, así que se guarda en caché por mucho tiempo. */
export function useEscalas() {
  return useQuery({
    queryKey: ['competencias', 'escalas'],
    queryFn: listarEscalas,
    staleTime: 30 * 60 * 1000,
  })
}
