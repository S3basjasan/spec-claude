import { useMutation, useQueryClient } from '@tanstack/react-query'
import { registrarImpacto, responderEncuesta, responderSeguimiento, type DatosEncuesta, type DatosImpacto, type DatosSeguimiento, type Impacto, type RespuestaEncuesta } from '../api'
import { reportesKey } from './claves'

/** Crea o reemplaza la evaluación de impacto de una acción. Las escrituras nunca se reintentan. */
export function useRegistrarImpacto(planAccionId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosImpacto): Promise<Impacto> => registrarImpacto(planAccionId, datos),
    retry: false,
    onSuccess: () => {
      // El ROI estimado del dashboard cambia con cada evaluación; el seguimiento 30-60-90 no.
      void queryClient.invalidateQueries({ queryKey: [...reportesKey, 'impactos'] })
      void queryClient.invalidateQueries({ queryKey: [...reportesKey, 'dashboard'] })
    },
  })
}

/** Responde la encuesta de satisfacción (una sola vez; no se reintenta). */
export function useResponderEncuesta() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosEncuesta): Promise<RespuestaEncuesta> => responderEncuesta(datos),
    retry: false,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: [...reportesKey, 'mi-progreso'] })
    },
  })
}

/** Responde un hito del seguimiento a 30, 60 o 90 días (una sola vez; no se reintenta). */
export function useResponderSeguimiento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosSeguimiento): Promise<void> => responderSeguimiento(datos),
    retry: false,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: [...reportesKey, 'mi-progreso'] })
    },
  })
}
