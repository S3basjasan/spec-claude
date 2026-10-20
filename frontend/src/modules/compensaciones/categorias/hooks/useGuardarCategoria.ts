import { useMutation, useQueryClient } from '@tanstack/react-query'
import { cambiarEstadoCategoria, crearCategoria, editarCategoria, type DatosCategoria, type EstadoCategoria } from '../api'
import { categoriasKey } from './useCategorias'

/** Tras guardar se invalida toda la familia: listados y detalles abiertos se refrescan. Las escrituras no se reintentan. */
function useInvalidarCategorias() {
  const queryClient = useQueryClient()
  return () => void queryClient.invalidateQueries({ queryKey: categoriasKey })
}

interface GuardarCategoria {
  datos: DatosCategoria
  /** Sin `fila`, crea; con ella, edita esa categoría con la versión (`updated_at`) leída. */
  fila?: { id: number; version: string }
}

export function useGuardarCategoria() {
  const invalidar = useInvalidarCategorias()
  return useMutation({
    mutationFn: ({ datos, fila }: GuardarCategoria) => (fila ? editarCategoria(fila.id, datos, fila.version) : crearCategoria(datos)),
    retry: false,
    onSuccess: invalidar,
  })
}

interface CambiarEstado {
  id: number
  version: string
  estado: EstadoCategoria
}

export function useCambiarEstadoCategoria() {
  const invalidar = useInvalidarCategorias()
  return useMutation({
    mutationFn: ({ id, version, estado }: CambiarEstado) => cambiarEstadoCategoria(id, estado, version),
    retry: false,
    onSuccess: invalidar,
  })
}
