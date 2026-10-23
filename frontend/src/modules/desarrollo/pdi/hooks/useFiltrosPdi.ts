import { useSearchParams } from 'react-router-dom'
import { ESTADOS_PDI, TIPOS_PDI, type EstadoPdi, type FiltrosGantt, type FiltrosLista, type TipoPdi } from '../api'

const POR_PAGINA = 15
const POR_PAGINA_GANTT = 10

function entero(valor: string | null): number | undefined {
  const n = Math.floor(Number(valor))

  return Number.isFinite(n) && n > 0 ? n : undefined
}

type Clave = 'tipo' | 'estado' | 'departamento' | 'responsable' | 'desde' | 'hasta' | 'page' | 'sinplan'

/** Filtros y página de la lista (y del Gantt), guardados en la URL para poder compartirlos. */
export function useFiltrosPdi() {
  const [params, setParams] = useSearchParams()
  const tipo = TIPOS_PDI.find((t) => t === params.get('tipo')) as TipoPdi | undefined
  const estado = ESTADOS_PDI.find((e) => e === params.get('estado')) as EstadoPdi | undefined

  const lista: FiltrosLista = {
    tipo,
    estado,
    departamento_id: entero(params.get('departamento')),
    responsable_id: entero(params.get('responsable')),
    fecha_desde: params.get('desde') ?? undefined,
    fecha_hasta: params.get('hasta') ?? undefined,
    page: entero(params.get('page')) ?? 1,
    per_page: POR_PAGINA,
  }

  const gantt: FiltrosGantt = {
    tipo,
    responsable_id: lista.responsable_id,
    departamento_id: lista.departamento_id,
    page: lista.page,
    per_page: POR_PAGINA_GANTT,
  }

  const cambiar = (cambios: Partial<Record<Clave, string>>) => {
    const siguiente = new URLSearchParams(params)
    for (const [clave, valor] of Object.entries(cambios)) {
      if (valor === undefined || valor === '') siguiente.delete(clave)
      else siguiente.set(clave, valor)
    }
    if (!('page' in cambios) && !('sinplan' in cambios)) siguiente.delete('page')
    setParams(siguiente, { replace: true })
  }

  const limpiar = () => {
    const siguiente = new URLSearchParams(params)
    for (const clave of ['tipo', 'estado', 'departamento', 'responsable', 'desde', 'hasta', 'page']) siguiente.delete(clave)
    setParams(siguiente, { replace: true })
  }

  const hayFiltros = ['tipo', 'estado', 'departamento', 'responsable', 'desde', 'hasta'].some((clave) => params.has(clave))

  return { lista, gantt, cambiar, limpiar, hayFiltros, paginaDeSinPlan: entero(params.get('sinplan')) ?? 1 }
}
