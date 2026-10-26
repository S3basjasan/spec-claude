import type { PoliticaAplicable, TipoPolitica } from '../politicas'
import type { ParametrosConservados, TipoActuacion } from './api'

const POLITICAS: Record<TipoActuacion, TipoPolitica> = {
  Ascenso: 'ascensos',
  Traslado: 'traslados',
  Permiso: 'permisos',
  Licencia: 'licencias',
  Vacaciones: 'vacaciones',
  Ausencia: 'ausencias',
}

export const politicaDe = (tipo: TipoActuacion): TipoPolitica => POLITICAS[tipo]

export type SituacionDeFormulario = 'cargando' | 'sinConfigurar' | 'sinSolicitudes' | 'noDisponible' | 'vigente'

/**
 * Traduce la consulta de condiciones aplicables a lo que decide el formulario. Una falla de consulta nunca se
 * interpreta como «sin requisitos»: bloquea la presentación y se distingue de las otras dos causas.
 */
export function situacionDeFormulario(consulta: { isPending: boolean; isError: boolean; data?: PoliticaAplicable }): {
  situacion: SituacionDeFormulario
  requisitos: ParametrosConservados | null
} {
  if (consulta.isPending) return { situacion: 'cargando', requisitos: null }
  if (consulta.isError || consulta.data === undefined) return { situacion: 'noDisponible', requisitos: null }
  if (consulta.data.situacion === 'SinConfigurar') return { situacion: 'sinConfigurar', requisitos: null }
  const requisitos = (consulta.data.parametros ?? {}) as ParametrosConservados
  if (consulta.data.situacion === 'NoAdmiteSolicitudes') return { situacion: 'sinSolicitudes', requisitos }

  return { situacion: 'vigente', requisitos }
}
