import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  actualizarCompetencia,
  actualizarComportamientos,
  actualizarPreguntas,
  type CompetenciaDetalle,
  type DatosSeccionCompetencia,
} from '../api'
import { competenciasKey } from './useCompetencias'

/**
 * Edita una sección de la competencia enviando la versión con la que se abrió el formulario. Las escrituras
 * nunca se reintentan; al terminar se refrescan el listado y el detalle.
 */
function useSeccion<Datos>(guardar: (datos: Datos, version: number) => Promise<CompetenciaDetalle>, version: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: Datos) => guardar(datos, version),
    retry: false,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: competenciasKey }),
  })
}

/** Edita nombre, descripción, tipo, escala y grados. */
export function useActualizarCompetencia(id: number, version: number) {
  return useSeccion<DatosSeccionCompetencia>((datos, v) => actualizarCompetencia(id, datos, v), version)
}

export function useActualizarComportamientos(id: number, version: number) {
  return useSeccion<string[][]>((datos, v) => actualizarComportamientos(id, datos, v), version)
}

export function useActualizarPreguntas(id: number, version: number) {
  return useSeccion<string[]>((datos, v) => actualizarPreguntas(id, datos, v), version)
}
