import { useMutation, useQueryClient } from '@tanstack/react-query'
import { cambiarEstadoPuesto, type EstadoPuesto } from '../api'
import { puestosKey } from './usePuestos'

interface Cambio {
  id: number
  estado: EstadoPuesto
}

/** Activa o desactiva un puesto. Las escrituras nunca se reintentan automáticamente. */
export function useCambiarEstadoPuesto() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, estado }: Cambio) => cambiarEstadoPuesto(id, estado),
    retry: false,
    // Sin esperar la recarga: el listado y el detalle se actualizan cuando llegan los datos nuevos.
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: puestosKey }),
  })
}
