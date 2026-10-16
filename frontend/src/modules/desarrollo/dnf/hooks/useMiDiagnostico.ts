import { useQuery } from '@tanstack/react-query'
import { miDiagnostico } from '../api'
import { dnfKey } from './claves'

/** Diagnóstico propio del Colaborador; `null` si aún no tiene una DNF. */
export function useMiDiagnostico() {
  return useQuery({
    queryKey: [...dnfKey, 'mi-diagnostico'],
    queryFn: miDiagnostico,
  })
}
