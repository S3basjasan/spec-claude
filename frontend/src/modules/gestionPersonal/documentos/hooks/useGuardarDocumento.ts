import { useMutation, useQueryClient } from '@tanstack/react-query'
import { archivarDocumento, cambiarVisibilidad, cargarDocumento, type DatosDeCarga } from '../api'
import { documentosKey } from './useDocumentos'

/** Tras guardar se vuelve a consultar la familia de documentos; las escrituras nunca se reintentan automáticamente. */
function useInvalidarDocumentos() {
  const queryClient = useQueryClient()
  return () => void queryClient.invalidateQueries({ queryKey: documentosKey })
}

interface Carga {
  datos: DatosDeCarga
  /** Clave de operación del diálogo abierto: un reintento tras un fallo de red la reutiliza. */
  clave: string
}

export function useCargarDocumento(colaboradorId: number) {
  const invalidar = useInvalidarDocumentos()
  return useMutation({
    mutationFn: ({ datos, clave }: Carga) => cargarDocumento(colaboradorId, datos, clave),
    retry: false,
    onSuccess: invalidar,
  })
}

/** Sin actualización optimista: la vista cambia solo cuando el servidor lo confirmó. */
export function useCambiarVisibilidad(colaboradorId: number) {
  const invalidar = useInvalidarDocumentos()
  return useMutation({
    mutationFn: ({ id, visible, version }: { id: number; visible: boolean; version: number }) => cambiarVisibilidad(colaboradorId, id, visible, version),
    retry: false,
    onSuccess: invalidar,
  })
}

export function useArchivarDocumento(colaboradorId: number) {
  const invalidar = useInvalidarDocumentos()
  return useMutation({
    mutationFn: ({ id, version }: { id: number; version: number }) => archivarDocumento(colaboradorId, id, version),
    retry: false,
    onSuccess: invalidar,
  })
}
