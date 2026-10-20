import { useSearchParams } from 'react-router-dom'
import { TIPOS_ACCION, type Agrupacion, type TipoAccion } from '../api'

interface Cambios {
  grupo?: Agrupacion
  desde?: string
  hasta?: string
  tipo?: TipoAccion | ''
  departamento?: number | ''
  plan?: number | ''
  participante?: number | ''
  page?: number
}

function entero(valor: string | null): number | '' {
  const n = Math.floor(Number(valor))
  return n > 0 ? n : ''
}

/**
 * Agrupación, filtros, participante elegido y página de la vista de calificaciones, guardados en la URL junto con
 * `?vista=calificaciones` para poder compartirlos. Sin fechas no se filtra por rango.
 */
export function useCalificacionesParams() {
  const [params, setParams] = useSearchParams()

  const grupo: Agrupacion = params.get('grupo') === 'participante' ? 'participante' : 'actividad'
  const desde = params.get('desde') ?? ''
  const hasta = params.get('hasta') ?? ''
  const tipoParam = params.get('tipo')
  const tipo: TipoAccion | '' = TIPOS_ACCION.includes(tipoParam as TipoAccion) ? (tipoParam as TipoAccion) : ''
  const departamento = entero(params.get('departamento'))
  const plan = entero(params.get('plan'))
  const participante = entero(params.get('participante'))
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)
  const hayFiltros = Boolean(desde || hasta || tipo || departamento || plan)

  const update = (cambios: Cambios) => {
    const next = new URLSearchParams(params)
    const poner = (clave: string, valor: string | number | '' | undefined) => {
      if (valor === undefined) return
      if (valor === '') next.delete(clave)
      else next.set(clave, String(valor))
    }
    if (cambios.grupo !== undefined) {
      if (cambios.grupo === 'actividad') next.delete('grupo')
      else next.set('grupo', cambios.grupo)
      next.delete('participante')
    }
    poner('desde', cambios.desde)
    poner('hasta', cambios.hasta)
    poner('tipo', cambios.tipo)
    poner('departamento', cambios.departamento)
    poner('plan', cambios.plan)
    poner('participante', cambios.participante)
    const cambioFiltro = [cambios.grupo, cambios.desde, cambios.hasta, cambios.tipo, cambios.departamento, cambios.plan].some((v) => v !== undefined)
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else if (cambios.page !== undefined || cambioFiltro) next.delete('page')
    setParams(next, { replace: true })
  }

  const limpiar = () => setParams({ vista: 'calificaciones', ...(grupo === 'participante' ? { grupo } : {}) }, { replace: true })

  return { grupo, desde, hasta, tipo, departamento, plan, participante, page, hayFiltros, update, limpiar }
}
