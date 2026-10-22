import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { listarAdecuaciones, obtenerAdecuacion, PREPARACIONES, type FiltrosEvaluaciones, type Preparacion } from '../api'
import { adecuacionKey } from './claves'

export function useEvaluaciones(filtros: FiltrosEvaluaciones, enabled = true) {
  return useQuery({
    queryKey: [...adecuacionKey, 'lista', filtros],
    queryFn: () => listarAdecuaciones(filtros),
    enabled,
    placeholderData: keepPreviousData,
  })
}

/** Detalle de solo lectura; siempre se lee de nuevo al abrirlo. */
export function useAdecuacionDetalle(id: number | null) {
  return useQuery({
    queryKey: [...adecuacionKey, 'detalle', id],
    queryFn: () => obtenerAdecuacion(id as number),
    enabled: id !== null,
    refetchOnMount: 'always',
  })
}

const POR_PAGINA = 15

function entero(valor: string | null): number | undefined {
  const n = Math.floor(Number(valor))

  return Number.isFinite(n) && n > 0 ? n : undefined
}

/** Filtros y página del historial, guardados en la URL para poder compartirlos. */
export function useFiltrosEvaluaciones() {
  const [params, setParams] = useSearchParams()
  const preparacion = PREPARACIONES.find((p) => p === params.get('preparacion')) as Preparacion | undefined

  const filtros: FiltrosEvaluaciones = {
    fecha_desde: params.get('desde') ?? undefined,
    fecha_hasta: params.get('hasta') ?? undefined,
    colaborador_id: entero(params.get('colaborador')),
    puesto_objetivo_id: entero(params.get('puesto')),
    preparacion,
    page: entero(params.get('page')) ?? 1,
    per_page: POR_PAGINA,
  }

  const cambiar = (cambios: Partial<Record<'desde' | 'hasta' | 'colaborador' | 'puesto' | 'preparacion' | 'page', string>>) => {
    const siguiente = new URLSearchParams(params)
    for (const [clave, valor] of Object.entries(cambios)) {
      if (valor === undefined || valor === '') siguiente.delete(clave)
      else siguiente.set(clave, valor)
    }
    if (!('page' in cambios)) siguiente.delete('page')
    setParams(siguiente, { replace: true })
  }

  const limpiar = () => {
    const siguiente = new URLSearchParams(params)
    for (const clave of ['desde', 'hasta', 'colaborador', 'puesto', 'preparacion', 'page']) siguiente.delete(clave)
    setParams(siguiente, { replace: true })
  }

  const hayFiltros = ['desde', 'hasta', 'colaborador', 'puesto', 'preparacion'].some((c) => params.get(c) !== null)

  return { filtros, cambiar, limpiar, hayFiltros }
}
