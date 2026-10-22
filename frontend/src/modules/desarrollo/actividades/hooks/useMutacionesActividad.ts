import { useMutation, useQueryClient } from '@tanstack/react-query'
import { actualizarActividad, crearActividad, registrarAsistencia, type ActividadDetalle, type DatosActividad, type RegistroAsistencia } from '../api'
import { actividadesKey } from './claves'

/** Crea (sin id) o reemplaza lugar y departamento (con id). Las escrituras nunca se reintentan. */
export function useGuardarActividad(id: number | null) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosActividad): Promise<ActividadDetalle> => (id === null ? crearActividad(datos) : actualizarActividad(id, datos)),
    retry: false,
    onSuccess: (actividad) => {
      queryClient.setQueryData([...actividadesKey, 'detalle', actividad.id], actividad)
      void queryClient.invalidateQueries({ queryKey: actividadesKey })
    },
  })
}

/** Guarda de una vez los cambios de asistencia de la actividad; envía la versión que se leyó. */
export function useRegistrarAsistencia(id: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ version, registros }: { version: number; registros: RegistroAsistencia[] }): Promise<ActividadDetalle> => registrarAsistencia(id, version, registros),
    retry: false,
    onSuccess: (actividad) => {
      queryClient.setQueryData([...actividadesKey, 'detalle', id], actividad)
      void queryClient.invalidateQueries({ queryKey: [...actividadesKey, 'lista'] })
      void queryClient.invalidateQueries({ queryKey: [...actividadesKey, 'calificaciones'] })
      void queryClient.invalidateQueries({ queryKey: [...actividadesKey, 'historial', id] })
    },
  })
}
