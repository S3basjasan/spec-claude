import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { obtenerMapa, PREPARACIONES, type FiltrosMapa, type Preparacion } from '../api'
import { adecuacionKey } from './claves'

export function useMapaDeTalento(filtros: FiltrosMapa) {
  return useQuery({
    queryKey: [...adecuacionKey, 'mapa', filtros],
    queryFn: () => obtenerMapa(filtros),
    placeholderData: keepPreviousData,
  })
}

const POR_PAGINA = 15

function entero(valor: string | null): number | undefined {
  const n = Math.floor(Number(valor))

  return Number.isFinite(n) && n > 0 ? n : undefined
}

/** Filtros y página del mapa, guardados en la URL para poder compartirlos. */
export function useFiltrosMapa() {
  const [params, setParams] = useSearchParams()

  const filtros: FiltrosMapa = {
    departamento_id: entero(params.get('departamento')),
    ruta_id: entero(params.get('ruta')),
    preparacion: PREPARACIONES.find((p) => p === params.get('preparacion')) as Preparacion | undefined,
    page: entero(params.get('page')) ?? 1,
    per_page: POR_PAGINA,
  }

  const cambiar = (cambios: Partial<Record<'departamento' | 'ruta' | 'preparacion' | 'page', string>>) => {
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
    for (const clave of ['departamento', 'ruta', 'preparacion', 'page']) siguiente.delete(clave)
    setParams(siguiente, { replace: true })
  }

  const hayFiltros = ['departamento', 'ruta', 'preparacion'].some((c) => params.get(c) !== null)

  return { filtros, cambiar, limpiar, hayFiltros }
}
