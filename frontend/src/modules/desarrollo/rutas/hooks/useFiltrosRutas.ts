import { useSearchParams } from 'react-router-dom'
import { ESTADOS_INSCRIPCION, ESTADOS_RUTA, TIPOS_RUTA, type EstadoInscripcion, type EstadoRuta, type PestanaRuta, type TipoRuta } from '../api'

interface CambiosLista {
  q?: string
  tipo?: TipoRuta | ''
  estado?: EstadoRuta | ''
  departamento?: number | ''
}

function entero(valor: string | null): number | '' {
  const n = Math.floor(Number(valor))
  return n > 0 ? n : ''
}

/** Búsqueda y filtros de la lista de rutas, guardados en la URL para poder compartirlos. */
export function useFiltrosRutas() {
  const [params, setParams] = useSearchParams()

  const q = params.get('q') ?? ''
  const tipoParam = params.get('tipo')
  const tipo: TipoRuta | '' = TIPOS_RUTA.includes(tipoParam as TipoRuta) ? (tipoParam as TipoRuta) : ''
  const estadoParam = params.get('estado')
  const estado: EstadoRuta | '' = ESTADOS_RUTA.includes(estadoParam as EstadoRuta) ? (estadoParam as EstadoRuta) : ''
  const departamento = entero(params.get('departamento'))
  const hayFiltros = Boolean(q || tipo || estado || departamento)

  const actualizar = (cambios: CambiosLista) => {
    const next = new URLSearchParams(params)
    const poner = (clave: string, valor: string | number | undefined) => {
      if (valor === undefined) return
      if (valor === '') next.delete(clave)
      else next.set(clave, String(valor))
    }
    poner('q', cambios.q)
    poner('tipo', cambios.tipo)
    poner('estado', cambios.estado)
    poner('departamento', cambios.departamento)
    setParams(next, { replace: true })
  }

  const limpiar = () => setParams({}, { replace: true })

  return { q, tipo, estado, departamento, hayFiltros, actualizar, limpiar }
}

interface CambiosEditor {
  pestana?: PestanaRuta
  nivel?: number | null
  estadoInscripcion?: EstadoInscripcion | ''
  paginaParticipantes?: number
  paginaHistorial?: number
}

/** Pestaña, nivel seleccionado, filtro de participantes y páginas del editor de una ruta, guardados en la URL. */
export function useParamsEditor() {
  const [params, setParams] = useSearchParams()

  const pestanaParam = params.get('pestana')
  const pestana: PestanaRuta = pestanaParam === 'participantes' || pestanaParam === 'historial' ? pestanaParam : 'escalera'
  const nivelParam = entero(params.get('nivel'))
  const nivel = nivelParam === '' ? null : nivelParam
  const estadoParam = params.get('estado')
  const estadoInscripcion: EstadoInscripcion | '' = ESTADOS_INSCRIPCION.includes(estadoParam as EstadoInscripcion) ? (estadoParam as EstadoInscripcion) : ''
  const paginaParticipantes = Math.max(1, Math.floor(Number(params.get('pp'))) || 1)
  const paginaHistorial = Math.max(1, Math.floor(Number(params.get('ph'))) || 1)

  const actualizar = (cambios: CambiosEditor) => {
    const next = new URLSearchParams(params)
    if (cambios.pestana !== undefined) {
      if (cambios.pestana === 'escalera') next.delete('pestana')
      else next.set('pestana', cambios.pestana)
    }
    if (cambios.nivel !== undefined) {
      if (cambios.nivel === null) next.delete('nivel')
      else next.set('nivel', String(cambios.nivel))
    }
    if (cambios.estadoInscripcion !== undefined) {
      if (cambios.estadoInscripcion === '') next.delete('estado')
      else next.set('estado', cambios.estadoInscripcion)
      next.delete('pp')
    }
    if (cambios.paginaParticipantes !== undefined) {
      if (cambios.paginaParticipantes > 1) next.set('pp', String(cambios.paginaParticipantes))
      else next.delete('pp')
    }
    if (cambios.paginaHistorial !== undefined) {
      if (cambios.paginaHistorial > 1) next.set('ph', String(cambios.paginaHistorial))
      else next.delete('ph')
    }
    setParams(next, { replace: true })
  }

  return { pestana, nivel, estadoInscripcion, paginaParticipantes, paginaHistorial, actualizar }
}
