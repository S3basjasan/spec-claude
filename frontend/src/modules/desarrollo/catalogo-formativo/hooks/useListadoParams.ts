import { useSearchParams } from 'react-router-dom'
import type { FiltroCobertura, FiltroEstado, TipoCompetencia } from '../api'

interface Cambios {
  q?: string
  tipo?: TipoCompetencia | ''
  area?: number | ''
  cobertura?: FiltroCobertura | ''
  estado?: FiltroEstado
  page?: number
}

const COBERTURAS: FiltroCobertura[] = ['con', 'sin', 'sin_oferta']

/** Búsqueda, filtros, página y competencia abierta de la matriz, guardados en la URL para poder compartirlos. */
export function useListadoParams() {
  const [params, setParams] = useSearchParams()

  const q = params.get('q') ?? ''
  const tipoParam = params.get('tipo')
  const tipo: TipoCompetencia | '' = tipoParam === 'Cardinal' || tipoParam === 'Especifica' ? tipoParam : ''
  const areaNumero = Math.floor(Number(params.get('area')))
  const area: number | '' = areaNumero > 0 ? areaNumero : ''
  const coberturaParam = params.get('cobertura') as FiltroCobertura | null
  const cobertura: FiltroCobertura | '' = coberturaParam && COBERTURAS.includes(coberturaParam) ? coberturaParam : ''
  const estadoParam = params.get('estado')
  // `Activo` es el valor por defecto y no se escribe en la URL.
  const estado: FiltroEstado = estadoParam === 'Inactivo' || estadoParam === 'Todos' ? estadoParam : 'Activo'
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)
  const competenciaNumero = Math.floor(Number(params.get('competencia')))
  const competencia = competenciaNumero > 0 ? competenciaNumero : null

  const update = (cambios: Cambios) => {
    const next = new URLSearchParams(params)
    const poner = (clave: string, valor: string | number | '' | undefined) => {
      if (valor === undefined) return
      if (valor === '') next.delete(clave)
      else next.set(clave, String(valor))
    }
    poner('q', cambios.q)
    poner('tipo', cambios.tipo)
    poner('area', cambios.area)
    poner('cobertura', cambios.cobertura)
    if (cambios.estado !== undefined) {
      if (cambios.estado === 'Activo') next.delete('estado')
      else next.set('estado', cambios.estado)
    }
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else next.delete('page')
    setParams(next, { replace: true })
  }

  /** Abre o cierra el panel/detalle de una competencia sin perder los filtros. */
  const abrir = (id: number | null) => {
    const next = new URLSearchParams(params)
    if (id === null) next.delete('competencia')
    else next.set('competencia', String(id))
    setParams(next)
  }

  return { q, tipo, area, cobertura, estado, page, competencia, update, abrir }
}
