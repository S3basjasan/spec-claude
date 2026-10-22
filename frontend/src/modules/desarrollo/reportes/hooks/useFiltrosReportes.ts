import { useSearchParams } from 'react-router-dom'
import { TIPOS_FORMACION, type FiltroEvaluado, type FiltrosReportes, type TipoFormacion, type VistaReportes } from '../api'
import { rangoDelAnio } from '../formato'

const VISTAS: VistaReportes[] = ['indicadores', 'detalle', 'seguimiento', 'impactos']

interface Cambios {
  desde?: string
  hasta?: string
  departamento?: number | ''
  tipo?: TipoFormacion | ''
  plan?: number | ''
  q?: string
  evaluado?: FiltroEvaluado | ''
  page?: number
}

function entero(valor: string | null): number | '' {
  const n = Math.floor(Number(valor))
  return n > 0 ? n : ''
}

/**
 * Vista, filtros, búsqueda y página de los reportes, guardados en la URL para poder compartirlos. Sin fechas en la URL
 * rige el año actual; «Limpiar filtros» lo restablece. Un cambio de filtro vuelve a la primera página.
 */
export function useFiltrosReportes() {
  const [params, setParams] = useSearchParams()
  const porDefecto = rangoDelAnio()

  const vistaParam = params.get('vista')
  const vista: VistaReportes = VISTAS.includes(vistaParam as VistaReportes) ? (vistaParam as VistaReportes) : 'indicadores'
  const desde = params.get('fecha_desde') ?? porDefecto.desde
  const hasta = params.get('fecha_hasta') ?? porDefecto.hasta
  const departamento = entero(params.get('departamento_id'))
  const tipoParam = params.get('tipo')
  const tipo: TipoFormacion | '' = TIPOS_FORMACION.includes(tipoParam as TipoFormacion) ? (tipoParam as TipoFormacion) : ''
  const plan = entero(params.get('plan_id'))
  const q = params.get('q') ?? ''
  const evaluadoParam = params.get('evaluado')
  const evaluado: FiltroEvaluado | '' = evaluadoParam === 'con' || evaluadoParam === 'sin' ? evaluadoParam : ''
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)

  const rangoValido = desde <= hasta
  const hayFiltros = desde !== porDefecto.desde || hasta !== porDefecto.hasta || departamento !== '' || tipo !== '' || plan !== ''

  /** Filtros para el backend; con un rango invertido no se consulta (se muestra el aviso del formulario). */
  const filtros: FiltrosReportes = {
    fecha_desde: desde,
    fecha_hasta: hasta,
    departamento_id: departamento === '' ? undefined : departamento,
    tipo: tipo === '' ? undefined : tipo,
    plan_id: plan === '' ? undefined : plan,
  }

  const actualizar = (cambios: Cambios) => {
    const next = new URLSearchParams(params)
    const poner = (clave: string, valor: string | number | '' | undefined, porDef?: string) => {
      if (valor === undefined) return
      if (valor === '' || valor === porDef) next.delete(clave)
      else next.set(clave, String(valor))
    }
    poner('fecha_desde', cambios.desde, porDefecto.desde)
    poner('fecha_hasta', cambios.hasta, porDefecto.hasta)
    poner('departamento_id', cambios.departamento)
    poner('tipo', cambios.tipo)
    poner('plan_id', cambios.plan)
    poner('q', cambios.q)
    poner('evaluado', cambios.evaluado)
    if (cambios.page !== undefined) poner('page', cambios.page === 1 ? '' : cambios.page)
    else next.delete('page')
    setParams(next, { replace: true })
  }

  const cambiarVista = (nueva: VistaReportes) => {
    const next = new URLSearchParams(params)
    if (nueva === 'indicadores') next.delete('vista')
    else next.set('vista', nueva)
    next.delete('page')
    next.delete('q')
    next.delete('evaluado')
    setParams(next, { replace: true })
  }

  const limpiar = () => {
    const next = new URLSearchParams(params)
    for (const clave of ['fecha_desde', 'fecha_hasta', 'departamento_id', 'tipo', 'plan_id', 'page']) next.delete(clave)
    setParams(next, { replace: true })
  }

  return { vista, desde, hasta, departamento, tipo, plan, q, evaluado, page, rangoValido, hayFiltros, filtros, actualizar, cambiarVista, limpiar }
}

export type FiltrosReportesUrl = ReturnType<typeof useFiltrosReportes>

