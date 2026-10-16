import { useSearchParams } from 'react-router-dom'
import { ESTADOS_PLAN, TIPOS_ACCION, type EstadoPlan, type TipoAccion } from '../api'
import { hoyIso } from '../fecha'

const FECHA = /^\d{4}-\d{2}-\d{2}$/
const MES = /^(\d{4})-(0[1-9]|1[0-2])$/

export type Vista = 'calendario' | 'lista'

function entero(valor: string | null): number | null {
  const numero = Math.floor(Number(valor))
  return numero > 0 ? numero : null
}

export interface CambiosAcciones {
  vista?: Vista
  estado?: EstadoPlan | ''
  tipo?: TipoAccion | ''
  competencia?: number | ''
  desde?: string
  hasta?: string
  mes?: string
  page?: number
}

/**
 * Pestaña, filtros comunes (estado del plan, tipo, competencia y rango de fechas), mes visible del calendario y página
 * de la lista, guardados en la URL. Cambiar de pestaña conserva los filtros.
 */
export function useAccionesParams() {
  const [params, setParams] = useSearchParams()

  const vista: Vista = params.get('vista') === 'lista' ? 'lista' : 'calendario'
  const estadoParam = params.get('estado') as EstadoPlan | null
  const estado: EstadoPlan | '' = estadoParam && ESTADOS_PLAN.includes(estadoParam) ? estadoParam : ''
  const tipoParam = params.get('tipo') as TipoAccion | null
  const tipo: TipoAccion | '' = tipoParam && TIPOS_ACCION.includes(tipoParam) ? tipoParam : ''
  const competencia: number | '' = entero(params.get('competencia')) ?? ''
  const desdeParam = params.get('desde')
  const hastaParam = params.get('hasta')
  const desde = desdeParam && FECHA.test(desdeParam) ? desdeParam : ''
  const hasta = hastaParam && FECHA.test(hastaParam) ? hastaParam : ''
  const page = Math.max(1, entero(params.get('page')) ?? 1)

  const mesParam = params.get('mes')
  const coincidencia = mesParam ? MES.exec(mesParam) : null
  const hoy = hoyIso()
  const anioMes = coincidencia ? { anio: Number(coincidencia[1]), mes: Number(coincidencia[2]) - 1 } : { anio: Number(hoy.slice(0, 4)), mes: Number(hoy.slice(5, 7)) - 1 }

  const update = (cambios: CambiosAcciones) => {
    const next = new URLSearchParams(params)
    const poner = (clave: string, valor: string | number | undefined) => {
      if (valor === undefined) return
      if (valor === '') next.delete(clave)
      else next.set(clave, String(valor))
    }
    if (cambios.vista !== undefined) {
      if (cambios.vista === 'calendario') next.delete('vista')
      else next.set('vista', cambios.vista)
    }
    poner('estado', cambios.estado)
    poner('tipo', cambios.tipo)
    poner('competencia', cambios.competencia)
    poner('desde', cambios.desde)
    poner('hasta', cambios.hasta)
    poner('mes', cambios.mes)
    // Cualquier cambio de filtro vuelve a la primera página.
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else next.delete('page')
    setParams(next, { replace: true })
  }

  /** Quita los filtros pero conserva la pestaña y el mes visible. */
  const limpiar = () => {
    const next = new URLSearchParams()
    if (params.get('vista')) next.set('vista', params.get('vista') as string)
    if (params.get('mes')) next.set('mes', params.get('mes') as string)
    setParams(next, { replace: true })
  }

  return { vista, estado, tipo, competencia, desde, hasta, page, anioMes, update, limpiar }
}

export function mesAIso(anio: number, mes: number): string {
  return `${anio}-${String(mes + 1).padStart(2, '0')}`
}
