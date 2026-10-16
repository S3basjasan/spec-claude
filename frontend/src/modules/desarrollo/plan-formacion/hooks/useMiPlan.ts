import { useQuery } from '@tanstack/react-query'
import { obtenerMiPlan } from '../api'
import { planesKey } from './claves'

/** Acciones asignadas al colaborador autenticado, su progreso y sus próximas actividades. */
export function useMiPlan() {
  return useQuery({
    queryKey: [...planesKey, 'mi-plan'],
    queryFn: obtenerMiPlan,
  })
}
