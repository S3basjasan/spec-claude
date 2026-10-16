import { useMutation, useQueryClient } from '@tanstack/react-query'
import { crearArea, guardarConfiguracion, renombrarArea, type DatosConfiguracion } from '../api'
import { catalogoKey } from './useCatalogo'

/** Guarda la configuración con la versión con la que se abrió el panel. Las escrituras nunca se reintentan. */
export function useGuardarConfiguracion(id: number, version: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosConfiguracion) => guardarConfiguracion(id, datos, version),
    retry: false,
    onSuccess: (detalle) => {
      // El panel parte de inmediato de lo guardado (con su nueva versión) mientras lista, cobertura e historial se refrescan.
      queryClient.setQueryData([...catalogoKey, 'detalle', id], detalle)
      void queryClient.invalidateQueries({ queryKey: catalogoKey })
    },
  })
}

export function useCrearArea() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (nombre: string) => crearArea(nombre),
    retry: false,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: catalogoKey }),
  })
}

export function useRenombrarArea() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, nombre }: { id: number; nombre: string }) => renombrarArea(id, nombre),
    retry: false,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: catalogoKey }),
  })
}
