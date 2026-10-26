import { useSearchParams } from 'react-router-dom'
import { FILTRO_TODOS, type FiltrosActuaciones } from '../api'
import type { ValoresDeFiltros } from '../components/FiltrosDeActuaciones'

const CLAVES = { tipo: 'tipo', estado: 'estado', grupo: 'grupo', desde: 'desde', hasta: 'hasta' } as const

/** Filtros y página del listado, guardados en la URL para poder compartirlos. */
export function useFiltrosDeActuaciones() {
  const [params, setParams] = useSearchParams()

  const colaboradorParam = Number(params.get('colaborador_id'))
  const valores: ValoresDeFiltros = {
    tipo: params.get(CLAVES.tipo) ?? FILTRO_TODOS,
    estado: params.get(CLAVES.estado) ?? FILTRO_TODOS,
    grupo: params.get(CLAVES.grupo) ?? FILTRO_TODOS,
    colaboradorId: Number.isInteger(colaboradorParam) && colaboradorParam > 0 ? colaboradorParam : null,
    desde: params.get(CLAVES.desde) ?? '',
    hasta: params.get(CLAVES.hasta) ?? '',
  }
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)

  const actualizar = (cambios: Partial<ValoresDeFiltros>, nuevaPagina?: number) => {
    const siguiente = new URLSearchParams(params)
    for (const [campo, clave] of Object.entries(CLAVES) as [keyof typeof CLAVES, string][]) {
      const valor = cambios[campo]
      if (valor === undefined) continue
      if (valor === '' || valor === FILTRO_TODOS) siguiente.delete(clave)
      else siguiente.set(clave, valor)
    }
    if (cambios.colaboradorId !== undefined) {
      if (cambios.colaboradorId === null) siguiente.delete('colaborador_id')
      else siguiente.set('colaborador_id', String(cambios.colaboradorId))
    }
    if (nuevaPagina !== undefined && nuevaPagina > 1) siguiente.set('page', String(nuevaPagina))
    else siguiente.delete('page')
    setParams(siguiente, { replace: true })
  }

  const limpiar = () => setParams(new URLSearchParams(), { replace: true })

  const filtros: FiltrosActuaciones = {
    tipo: valores.tipo,
    estado: valores.estado,
    grupo: valores.grupo,
    colaborador_id: valores.colaboradorId ?? undefined,
    desde: valores.desde,
    hasta: valores.hasta,
    page,
  }

  return { valores, filtros, page, actualizar, limpiar }
}
