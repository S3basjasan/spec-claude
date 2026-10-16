import { useSearchParams } from 'react-router-dom'
import type { FiltroEstado } from '../api'

interface Cambios {
  q?: string
  estado?: FiltroEstado
  /** `null` quita el filtro de unidad. */
  unidad?: number | null
  page?: number
}

const ESTADOS: FiltroEstado[] = ['Ocupada', 'Vacante', 'Suprimida', 'Todas']

/** Búsqueda, filtros de estado y de unidad y página del listado, guardados en la URL para poder compartirlos. */
export function useListadoParams() {
  const [params, setParams] = useSearchParams()

  const q = params.get('q') ?? ''
  const estadoParam = params.get('estado') as FiltroEstado | null
  // `Vigentes` es el valor por defecto y no se escribe en la URL.
  const estado: FiltroEstado = estadoParam !== null && ESTADOS.includes(estadoParam) ? estadoParam : 'Vigentes'
  const unidadParam = Math.floor(Number(params.get('unidad')))
  const unidad = unidadParam > 0 ? unidadParam : null
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)

  const update = (cambios: Cambios) => {
    const next = new URLSearchParams(params)
    if (cambios.q !== undefined) {
      if (cambios.q) next.set('q', cambios.q)
      else next.delete('q')
    }
    if (cambios.estado !== undefined) {
      if (cambios.estado === 'Vigentes') next.delete('estado')
      else next.set('estado', cambios.estado)
    }
    if (cambios.unidad !== undefined) {
      if (cambios.unidad === null) next.delete('unidad')
      else next.set('unidad', String(cambios.unidad))
    }
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else next.delete('page')
    setParams(next, { replace: true })
  }

  return { q, estado, unidad, page, update }
}
