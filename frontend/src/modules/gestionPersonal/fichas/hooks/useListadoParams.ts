import { useSearchParams } from 'react-router-dom'
import { FILTRO_TODOS } from '../api'

interface Cambios {
  q?: string
  estado?: string
  unidadId?: number | null
  page?: number
}

/** Búsqueda, estado, unidad y página del listado, guardados en la URL para poder compartirlos. */
export function useListadoParams() {
  const [params, setParams] = useSearchParams()

  const q = params.get('q') ?? ''
  const estado = params.get('estado') ?? FILTRO_TODOS
  const unidadParam = Number(params.get('unidad_id'))
  const unidadId = Number.isInteger(unidadParam) && unidadParam > 0 ? unidadParam : null
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)

  const update = (cambios: Cambios) => {
    const next = new URLSearchParams(params)
    if (cambios.q !== undefined) {
      if (cambios.q) next.set('q', cambios.q)
      else next.delete('q')
    }
    if (cambios.estado !== undefined) {
      if (cambios.estado === FILTRO_TODOS) next.delete('estado')
      else next.set('estado', cambios.estado)
    }
    if (cambios.unidadId !== undefined) {
      if (cambios.unidadId === null) next.delete('unidad_id')
      else next.set('unidad_id', String(cambios.unidadId))
    }
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else next.delete('page')
    setParams(next, { replace: true })
  }

  return { q, estado, unidadId, page, update }
}
