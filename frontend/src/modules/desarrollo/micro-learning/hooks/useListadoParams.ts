import { useSearchParams } from 'react-router-dom'
import { TIPOS_MICRO, type FiltroEstadoMicro, type TipoMicro } from '../api'

interface Cambios {
  q?: string
  tipo?: TipoMicro | ''
  estado?: FiltroEstadoMicro
  competencia?: number | ''
  page?: number
  tab?: PestanaAprendizaje
}

export type PestanaAprendizaje = 'asignado' | 'completado'

const ESTADOS: FiltroEstadoMicro[] = ['Borrador', 'Publicado', 'Archivado', 'Todos']

/**
 * Búsqueda, filtros, página y pestaña de micro-learning, guardados en la URL para poder compartirlos. `Activos`
 * (Borrador y Publicado) y la pestaña «Asignado» son los valores por defecto y no se escriben.
 */
export function useListadoParams() {
  const [params, setParams] = useSearchParams()

  const q = params.get('q') ?? ''
  const tipoParam = params.get('tipo')
  const tipo: TipoMicro | '' = TIPOS_MICRO.includes(tipoParam as TipoMicro) ? (tipoParam as TipoMicro) : ''
  const estadoParam = params.get('estado') as FiltroEstadoMicro | null
  const estado: FiltroEstadoMicro = estadoParam && ESTADOS.includes(estadoParam) ? estadoParam : 'Activos'
  const competenciaNumero = Math.floor(Number(params.get('competencia')))
  const competencia: number | '' = competenciaNumero > 0 ? competenciaNumero : ''
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)
  const tab: PestanaAprendizaje = params.get('tab') === 'completado' ? 'completado' : 'asignado'

  const update = (cambios: Cambios) => {
    const next = new URLSearchParams(params)
    const poner = (clave: string, valor: string | number | '' | undefined) => {
      if (valor === undefined) return
      if (valor === '') next.delete(clave)
      else next.set(clave, String(valor))
    }
    poner('q', cambios.q)
    poner('tipo', cambios.tipo)
    poner('competencia', cambios.competencia)
    if (cambios.estado !== undefined) {
      if (cambios.estado === 'Activos') next.delete('estado')
      else next.set('estado', cambios.estado)
    }
    if (cambios.tab !== undefined) {
      if (cambios.tab === 'asignado') next.delete('tab')
      else next.set('tab', cambios.tab)
    }
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else if (cambios.page !== undefined || cambios.q !== undefined || cambios.tipo !== undefined || cambios.estado !== undefined || cambios.competencia !== undefined) next.delete('page')
    setParams(next, { replace: true })
  }

  return { q, tipo, estado, competencia, page, tab, update }
}
