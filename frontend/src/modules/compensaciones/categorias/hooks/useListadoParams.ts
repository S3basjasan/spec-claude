import { useSearchParams } from 'react-router-dom'
import { ESTADO_INICIAL, LIMITE_BUSQUEDA, type FiltroEstadoCategoria } from '../api'

interface Cambios {
  q?: string
  /** `null` quita el filtro de competencia. */
  competencia?: number | null
  estado?: FiltroEstadoCategoria
  page?: number
}

const ESTADOS: FiltroEstadoCategoria[] = ['Activo', 'Inactivo', 'Todos']

/**
 * Búsqueda, filtros y página del listado, guardados en la URL (`q`, `competencia`, `estado`, `page`) para
 * poder recargarlos y compartirlos. Cualquier cambio de búsqueda o filtro vuelve a la página 1.
 */
export function useListadoParams() {
  const [params, setParams] = useSearchParams()

  const q = (params.get('q') ?? '').slice(0, LIMITE_BUSQUEDA)
  const competenciaParam = Math.floor(Number(params.get('competencia')))
  const competencia = competenciaParam > 0 ? competenciaParam : undefined
  const estadoParam = params.get('estado')
  const estado = ESTADOS.find((valor) => valor === estadoParam) ?? ESTADO_INICIAL
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)

  const update = (cambios: Cambios) => {
    const next = new URLSearchParams(params)

    if (cambios.q !== undefined) {
      if (cambios.q) next.set('q', cambios.q)
      else next.delete('q')
    }
    if (cambios.competencia !== undefined) {
      if (cambios.competencia === null) next.delete('competencia')
      else next.set('competencia', String(cambios.competencia))
    }
    if (cambios.estado !== undefined) {
      if (cambios.estado === ESTADO_INICIAL) next.delete('estado')
      else next.set('estado', cambios.estado)
    }
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else next.delete('page')

    setParams(next, { replace: true })
  }

  return { q, competencia, estado, page, update }
}
