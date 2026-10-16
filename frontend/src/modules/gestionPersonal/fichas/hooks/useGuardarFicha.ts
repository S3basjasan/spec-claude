import { useMutation, useQueryClient } from '@tanstack/react-query'
import { actualizarColaborador, crearColaborador, type DatosFicha } from '../api'
import { colaboradoresKey, miFichaKey } from './useColaboradores'

interface NuevaFicha {
  datos: DatosFicha
  /** Clave de operación (Idempotency-Key) generada al abrir el formulario. */
  clave: string
}

/** Crea una ficha. Las escrituras nunca se reintentan automáticamente. */
export function useCrearColaborador() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ datos, clave }: NuevaFicha) => crearColaborador(datos, clave),
    retry: false,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: colaboradoresKey }),
  })
}

/** Corrige una ficha enviando solo los campos cambiados y la versión con la que se abrió. */
export function useActualizarColaborador(id: number, version: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (cambios: Partial<DatosFicha>) => actualizarColaborador(id, cambios, version),
    retry: false,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: colaboradoresKey })
      void queryClient.invalidateQueries({ queryKey: miFichaKey })
    },
  })
}
