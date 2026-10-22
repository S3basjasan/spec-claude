import { useSearchParams } from 'react-router-dom'
import type { TipoCompetencia } from '../api'

interface Cambios {
  q?: string
  tipo?: TipoCompetencia | ''
  page?: number
}

/** Búsqueda, tipo y página del listado, guardados en la URL para poder compartirlos. */
export function useListadoParams() {
  const [params, setParams] = useSearchParams()

  const q = params.get('q') ?? ''
  const tipoParam = params.get('tipo')
  const tipo: TipoCompetencia | undefined = tipoParam === 'Cardinal' || tipoParam === 'Especifica' ? tipoParam : undefined
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
    // Cambiar la búsqueda o el tipo vuelve a la primera página, salvo que se pida otra.
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else next.delete('page')
    setParams(next, { replace: true })
  }

  return { q, tipo, page, update }
}
