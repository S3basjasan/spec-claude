import { useSearchParams } from 'react-router-dom'

interface Cambios {
  q?: string
  /** `null` quita el filtro de unidad. */
  unidad?: number | null
}

/** Búsqueda y filtro de unidad del organigrama, guardados en la URL para poder compartirlos. */
export function useListadoParams() {
  const [params, setParams] = useSearchParams()

  const q = params.get('q') ?? ''
  const unidadParam = Math.floor(Number(params.get('unidad')))
  const unidad = unidadParam > 0 ? unidadParam : null

  const update = (cambios: Cambios) => {
    const next = new URLSearchParams(params)
    if (cambios.q !== undefined) {
      if (cambios.q) next.set('q', cambios.q)
      else next.delete('q')
    }
    if (cambios.unidad !== undefined) {
      if (cambios.unidad === null) next.delete('unidad')
      else next.set('unidad', String(cambios.unidad))
    }
    setParams(next, { replace: true })
  }

  return { q, unidad, update }
}
