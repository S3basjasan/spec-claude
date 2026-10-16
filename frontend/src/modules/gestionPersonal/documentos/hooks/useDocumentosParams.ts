import { useSearchParams } from 'react-router-dom'
import type { EstadoDocumento } from '../api'

interface Cambios {
  q?: string
  categoriaId?: number | null
  estado?: EstadoDocumento
  page?: number
}

/** Búsqueda, categoría, estado y página del listado, guardados en la URL para poder compartirlos. */
export function useDocumentosParams() {
  const [params, setParams] = useSearchParams()

  const q = params.get('q') ?? ''
  const categoriaParam = Number(params.get('categoria_id'))
  const categoriaId = Number.isInteger(categoriaParam) && categoriaParam > 0 ? categoriaParam : null
  const estado: EstadoDocumento = params.get('estado') === 'Archivado' ? 'Archivado' : 'Disponible'
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)

  const update = (cambios: Cambios) => {
    const next = new URLSearchParams(params)
    if (cambios.q !== undefined) {
      if (cambios.q) next.set('q', cambios.q)
      else next.delete('q')
    }
    if (cambios.categoriaId !== undefined) {
      if (cambios.categoriaId === null) next.delete('categoria_id')
      else next.set('categoria_id', String(cambios.categoriaId))
    }
    if (cambios.estado !== undefined) {
      if (cambios.estado === 'Disponible') next.delete('estado')
      else next.set('estado', cambios.estado)
    }
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else next.delete('page')
    setParams(next, { replace: true })
  }

  return { q, categoriaId, estado, page, update }
}
