import { useSearchParams } from 'react-router-dom'
import { ESTADOS_PLAN, type EstadoPlan } from '../api'

function entero(valor: string | null): number | null {
  const numero = Math.floor(Number(valor))
  return numero > 0 ? numero : null
}

export interface CambiosPlanes {
  anio?: number | ''
  estado?: EstadoPlan | ''
  q?: string
  page?: number
}

/** Filtros, búsqueda y página de la tabla de planes, guardados en la URL para poder compartirlos. */
export function usePlanParams() {
  const [params, setParams] = useSearchParams()

  const anio: number | '' = entero(params.get('anio')) ?? ''
  const estadoParam = params.get('estado') as EstadoPlan | null
  const estado: EstadoPlan | '' = estadoParam && ESTADOS_PLAN.includes(estadoParam) ? estadoParam : ''
  const q = params.get('q') ?? ''
  const page = Math.max(1, entero(params.get('page')) ?? 1)

  const update = (cambios: CambiosPlanes) => {
    const next = new URLSearchParams(params)
    const poner = (clave: string, valor: string | number | undefined) => {
      if (valor === undefined) return
      if (valor === '') next.delete(clave)
      else next.set(clave, String(valor))
    }
    poner('anio', cambios.anio)
    poner('estado', cambios.estado)
    poner('q', cambios.q)
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else next.delete('page')
    setParams(next, { replace: true })
  }

  const limpiar = () => setParams(new URLSearchParams(), { replace: true })

  return { anio, estado, q, page, update, limpiar }
}
