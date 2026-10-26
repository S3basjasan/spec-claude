import { toApiError } from '../../../shared/api/httpClient'
import { movimientosText } from './text'

const text = movimientosText.errors

export interface ProblemaDeActuacion {
  /** Texto para mostrar; distingue la causa (política, evaluación, estructura, documentos) de un error genérico. */
  mensaje: string
  /** Conflicto de edición o de estado: se explica y se ofrece actualizar, nunca se reenvía solo. */
  conflicto: boolean
  /** Errores por campo del servidor (422), con el nombre del campo del cuerpo. */
  campos: Record<string, string>
  /** El servidor respondió 404: la solicitud o la ficha no existe o no es accesible. */
  noEncontrado: boolean
  /** Código de negocio del servidor, si lo hubo. */
  codigo?: string
  /** Incidencia de aplicación devuelta por un reintento. */
  incidencia?: { codigo: string; detalle: string | null }
}

const MENSAJES: Record<string, string> = {
  ANTICIPACION_INSUFICIENTE: text.advance,
  FECHA_PASADA: text.pastDate,
  AUSENCIA_FUTURA: text.futureAbsence,
  JUSTIFICACION_REQUERIDA: text.reasonRequired,
  DOCUMENTO_REQUERIDO: text.supportRequired,
  SIN_VINCULO_VIGENTE: text.noBond,
  POSICION_NO_DISPONIBLE: text.position,
  POLITICA_SIN_CONFIGURAR: text.policyNotConfigured,
  POLITICA_NO_ADMITE_SOLICITUDES: text.policyNoRequests,
  POLITICA_NO_DISPONIBLE: text.policyUnavailable,
  ESTRUCTURA_NO_VERIFICABLE: text.structureUnavailable,
  EVALUACION_NO_VERIFICABLE: text.evaluationUnavailable,
  MOVIMIENTO_INCOMPATIBLE: text.incompatible,
  APLICACION_NO_PROCEDE: text.notApplicable,
  ESTADO_NO_RESOLUBLE: text.notResolvable,
  CRUCE_SIN_RECONOCER: text.crossing,
  CLAVE_DE_OPERACION_REUTILIZADA: text.keyReused,
  CLAVE_DE_OPERACION_REQUERIDA: text.keyRequired,
  SIN_FICHA_ASOCIADA: text.noFicha,
  FICHA_NO_ENCONTRADA: text.noRecord,
  SOLICITUD_NO_ENCONTRADA: text.notFound,
}

/** Traduce un error de la API de movimientos y novedades a lo que muestra la pantalla. */
export function problemaDeActuacion(error: unknown): ProblemaDeActuacion {
  const api = toApiError(error)
  const campos: Record<string, string> = {}
  for (const [campo, mensajes] of Object.entries(api.fieldErrors)) {
    if (mensajes[0]) campos[campo] = mensajes[0]
  }

  if (api.isNetworkError) return { mensaje: text.network, conflicto: false, campos, noEncontrado: false }

  const codigo = api.code
  const conflicto = codigo === 'SOLICITUD_MODIFICADA' || (api.status === 409 && codigo === undefined)
  const incidencia = codigo === 'INCIDENCIA_DE_APLICACION' ? leerIncidencia(api.data) : undefined

  let mensaje = codigo !== undefined ? MENSAJES[codigo] : undefined
  if (conflicto) mensaje = text.conflict
  if (incidencia) mensaje = movimientosText.resolution.stillBlocked
  mensaje ??= api.status === 422 && Object.keys(campos).length > 0 ? Object.values(campos)[0] : text.unexpected

  return { mensaje, conflicto, campos, noEncontrado: api.status === 404, codigo, incidencia }
}

function leerIncidencia(datos: Record<string, unknown> | undefined): { codigo: string; detalle: string | null } | undefined {
  const bruto = datos?.incidencia
  if (bruto === null || typeof bruto !== 'object') return undefined
  const { codigo, detalle } = bruto as { codigo?: unknown; detalle?: unknown }
  return typeof codigo === 'string' ? { codigo, detalle: typeof detalle === 'string' ? detalle : null } : undefined
}
