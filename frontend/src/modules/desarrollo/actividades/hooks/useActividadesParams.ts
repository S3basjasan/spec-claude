import { useSearchParams } from 'react-router-dom'
import { ESTADOS_ACTIVIDAD, TIPOS_ACCION, type EstadoActividad, type TipoAccion, type VistaAdmin } from '../api'

interface Cambios {
  q?: string
  tipo?: TipoAccion | ''
  estado?: EstadoActividad | ''
  departamento?: number | ''
  competencia?: number | ''
  plan?: number | ''
  desde?: string
  hasta?: string
  page?: number
}

/** Rango del año actual, valor por defecto de la lista (FR-026). */
export function rangoDelAnio(hoy: Date = new Date()): { desde: string; hasta: string } {
  const anio = hoy.getFullYear()
  return { desde: `${anio}-01-01`, hasta: `${anio}-12-31` }
}

function entero(valor: string | null): number | '' {
  const n = Math.floor(Number(valor))
  return n > 0 ? n : ''
}

/**
 * Vista, búsqueda, filtros y página de la tabla de actividades, guardados en la URL para poder compartirlos.
 * Sin fechas en la URL se usa el año actual; «Limpiar filtros» lo restablece.
 */
export function useActividadesParams() {
  const [params, setParams] = useSearchParams()
  const porDefecto = rangoDelAnio()

  const vista: VistaAdmin = params.get('vista') === 'calificaciones' ? 'calificaciones' : 'actividades'
  const q = params.get('q') ?? ''
  const tipoParam = params.get('tipo')
  const tipo: TipoAccion | '' = TIPOS_ACCION.includes(tipoParam as TipoAccion) ? (tipoParam as TipoAccion) : ''
  const estadoParam = params.get('estado')
  const estado: EstadoActividad | '' = ESTADOS_ACTIVIDAD.includes(estadoParam as EstadoActividad) ? (estadoParam as EstadoActividad) : ''
  const departamento = entero(params.get('departamento'))
  const competencia = entero(params.get('competencia'))
  const plan = entero(params.get('plan'))
  const desde = params.get('desde') ?? porDefecto.desde
  const hasta = params.get('hasta') ?? porDefecto.hasta
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)

  const hayFiltros = Boolean(q || tipo || estado || departamento || competencia || plan || desde !== porDefecto.desde || hasta !== porDefecto.hasta)

  const update = (cambios: Cambios) => {
    const next = new URLSearchParams(params)
    const poner = (clave: string, valor: string | number | '' | undefined, porDef?: string) => {
      if (valor === undefined) return
      if (valor === '' || valor === porDef) next.delete(clave)
      else next.set(clave, String(valor))
    }
    poner('q', cambios.q)
    poner('tipo', cambios.tipo)
    poner('estado', cambios.estado)
    poner('departamento', cambios.departamento)
    poner('competencia', cambios.competencia)
    poner('plan', cambios.plan)
    poner('desde', cambios.desde, porDefecto.desde)
    poner('hasta', cambios.hasta, porDefecto.hasta)
    if (cambios.page !== undefined && cambios.page > 1) next.set('page', String(cambios.page))
    else next.delete('page')
    setParams(next, { replace: true })
  }

  /** Cambia de pestaña y descarta los filtros de la otra vista. */
  const cambiarVista = (nueva: VistaAdmin) => setParams(nueva === 'calificaciones' ? { vista: 'calificaciones' } : {}, { replace: true })

  const limpiar = () => setParams(vista === 'calificaciones' ? { vista: 'calificaciones' } : {}, { replace: true })

  return { vista, q, tipo, estado, departamento, competencia, plan, desde, hasta, page, hayFiltros, update, cambiarVista, limpiar }
}
