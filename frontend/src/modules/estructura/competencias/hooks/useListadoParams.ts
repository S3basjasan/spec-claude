import { useSearchParams } from 'react-router-dom'
import type { FiltroEstado, TipoCompetencia } from '../api'

interface Cambios {
  q?: string
  tipo?: TipoCompetencia | ''
  estado?: FiltroEstado
  page?: number
}

/** Búsqueda, filtros y página del listado, guardados en la URL para poder compartirlos. */
export function useListadoParams() {
  const [params, setParams] = useSearchParams()

  const q = params.get('q') ?? ''
  const tipoParam = params.get('tipo')
  const tipo: TipoCompetencia | undefined = tipoParam === 'Cardinal' || tipoParam === 'Especifica' ? tipoParam : undefined
  const estadoParam = params.get('estado')
  // `Activo` es el valor por defecto y no se escribe en la URL.
  const estado: FiltroEstado = estadoParam === 'Inactivo' || estadoParam === 'Todos' ? estadoParam : 'Activo'
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)

  const update = (cambios: Cambios) => {
    const next = new URLSearchParams(params)
    if (cambios.q !== undefined) {
      if (cambios.q) next.set('q', cambios.q)
      else next.delete('q')
    }
    if (cambios.tipo !== undefined) {
      if (cambios.tipo) next.set('tipo', cambios.tipo)
      else next.delete('tipo')
    }
    if (cambios.estado !== undefined) {
      if (cambios.estado === 'Activo') next.delete('estado')
      else next.set('estado', cambios.estado)
    }
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else next.delete('page')
    setParams(next, { replace: true })
  }

  return { q, tipo, estado, page, update }
}
