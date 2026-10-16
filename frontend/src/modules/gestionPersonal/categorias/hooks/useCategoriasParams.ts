import { useSearchParams } from 'react-router-dom'
import { ESTADO_INICIAL, type FiltroEstadoCategoria } from '../api'

interface Cambios {
  q?: string
  estado?: FiltroEstadoCategoria
  page?: number
}

const ESTADOS: FiltroEstadoCategoria[] = ['Activa', 'Inactiva', 'Todas']

/** Búsqueda, estado y página del listado, guardados en la URL para poder compartirlos. */
export function useCategoriasParams() {
  const [params, setParams] = useSearchParams()

  const q = params.get('q') ?? ''
  const estadoParam = params.get('estado')
  const estado = ESTADOS.find((valor) => valor === estadoParam) ?? ESTADO_INICIAL
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)

  const update = (cambios: Cambios) => {
    const next = new URLSearchParams(params)
    if (cambios.q !== undefined) {
      if (cambios.q) next.set('q', cambios.q)
      else next.delete('q')
    }
    if (cambios.estado !== undefined) {
      if (cambios.estado === ESTADO_INICIAL) next.delete('estado')
      else next.set('estado', cambios.estado)
    }
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else next.delete('page')
    setParams(next, { replace: true })
  }

  return { q, estado, page, update }
}
