import { useMutation, useQueryClient } from '@tanstack/react-query'
import { cambiarEstadoCategoria, crearCategoria, editarCategoria, type EstadoCategoria } from '../api'
import { categoriasKey } from './useCategorias'

/** Tras guardar se invalida toda la familia: listados y selectores abiertos se refrescan. Las escrituras no se reintentan. */
function useInvalidarCategorias() {
  const queryClient = useQueryClient()
  return () => void queryClient.invalidateQueries({ queryKey: categoriasKey })
}

interface GuardarCategoria {
  nombre: string
  /** Sin `fila`, crea; con ella, corrige esa categoría con su versión vigente. */
  fila?: { id: number; version: number }
}

export function useGuardarCategoria() {
  const invalidar = useInvalidarCategorias()
  return useMutation({
    mutationFn: ({ nombre, fila }: GuardarCategoria) =>
      fila ? editarCategoria(fila.id, nombre, fila.version) : crearCategoria(nombre),
    retry: false,
    onSuccess: invalidar,
  })
}

interface CambiarEstado {
  id: number
  version: number
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
