import { useSearchParams } from 'react-router-dom'
import { ESTADOS, PRIORIDADES, type EstadoBrecha, type Prioridad } from '../api'
import { SIN_FILTROS, type FiltrosDiagnostico } from '../orden'

const FECHA = /^\d{4}-\d{2}-\d{2}$/

function entero(valor: string | null): number | null {
  const numero = Math.floor(Number(valor))
  return numero > 0 ? numero : null
}

export interface CambiosHistorial {
  fecha_desde?: string
  fecha_hasta?: string
  colaborador?: number | ''
  departamento?: number | ''
  prioridad?: Prioridad | ''
  page?: number
}

/** Filtros y página del historial de DNF, guardados en la URL para poder compartirlos. */
export function useHistorialParams() {
  const [params, setParams] = useSearchParams()

  const desde = params.get('desde')
  const hasta = params.get('hasta')
  const fecha_desde = desde && FECHA.test(desde) ? desde : ''
  const fecha_hasta = hasta && FECHA.test(hasta) ? hasta : ''
  const colaborador: number | '' = entero(params.get('colaborador')) ?? ''
  const departamento: number | '' = entero(params.get('departamento')) ?? ''
  const prioridadParam = params.get('prioridad') as Prioridad | null
  const prioridad: Prioridad | '' = prioridadParam && PRIORIDADES.includes(prioridadParam) ? prioridadParam : ''
  const page = Math.max(1, entero(params.get('page')) ?? 1)

  const update = (cambios: CambiosHistorial) => {
    const next = new URLSearchParams(params)
    const poner = (clave: string, valor: string | number | '' | undefined) => {
      if (valor === undefined) return
      if (valor === '') next.delete(clave)
      else next.set(clave, String(valor))
    }
    poner('desde', cambios.fecha_desde)
    poner('hasta', cambios.fecha_hasta)
    poner('colaborador', cambios.colaborador)
    poner('departamento', cambios.departamento)
    poner('prioridad', cambios.prioridad)
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else next.delete('page')
    setParams(next, { replace: true })
  }

  const limpiar = () => setParams(new URLSearchParams(), { replace: true })

  return { fecha_desde, fecha_hasta, colaborador, departamento, prioridad, page, update, limpiar }
}

/** Filtros del diagnóstico (prioridad, estado, «solo con brecha») y colaborador elegido, guardados en la URL del detalle. */
export function useFiltrosDiagnosticoUrl() {
  const [params, setParams] = useSearchParams()

  const prioridadParam = params.get('prioridad') as Prioridad | null
  const estadoParam = params.get('estado') as EstadoBrecha | null
  const filtros: FiltrosDiagnostico = {
    prioridad: prioridadParam && PRIORIDADES.includes(prioridadParam) ? prioridadParam : '',
    estado: estadoParam && ESTADOS.includes(estadoParam) ? estadoParam : '',
    soloConBrecha: params.get('solo_brecha') === '1',
  }
  const colaborador = entero(params.get('colaborador'))

  const cambiarFiltros = (nuevos: FiltrosDiagnostico) => {
    const next = new URLSearchParams(params)
    if (nuevos.prioridad === SIN_FILTROS.prioridad) next.delete('prioridad')
    else next.set('prioridad', nuevos.prioridad)
    if (nuevos.estado === SIN_FILTROS.estado) next.delete('estado')
    else next.set('estado', nuevos.estado)
    if (nuevos.soloConBrecha) next.set('solo_brecha', '1')
    else next.delete('solo_brecha')
    setParams(next, { replace: true })
  }

  const elegirColaborador = (id: number | null) => {
    const next = new URLSearchParams(params)
    next.delete('prioridad')
    next.delete('estado')
    next.delete('solo_brecha')
    if (id === null) next.delete('colaborador')
    else next.set('colaborador', String(id))
    setParams(next)
  }

  return { filtros, colaborador, cambiarFiltros, elegirColaborador }
}
