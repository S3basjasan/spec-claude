import { useMutation, useQueryClient } from '@tanstack/react-query'
import { generarDnf, type SolicitudDnf } from '../api'
import { dnfKey } from './claves'

/** Genera una DNF. Es una escritura: nunca se reintenta automáticamente. */
export function useGenerarDnf() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (solicitud: SolicitudDnf) => generarDnf(solicitud),
    onSuccess: () => {
      // El historial, las opciones y el diagnóstico propio cambian con una nueva DNF.
      void queryClient.invalidateQueries({ queryKey: dnfKey })
    },
  })
}
