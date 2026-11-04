import { useMutation, useQueryClient } from '@tanstack/react-query'
import { guardarParametros, type CambioDeParametro } from '../api'
import { parametrosKey } from './useParametros'

/** Las escrituras no se reintentan. Al guardar, la consulta se actualiza con la respuesta del servidor. */
export function useGuardarParametros() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (cambios: CambioDeParametro[]) => guardarParametros(cambios),
    retry: false,
    onSuccess: (parametros) => queryClient.setQueryData(parametrosKey, parametros),
  })
}
