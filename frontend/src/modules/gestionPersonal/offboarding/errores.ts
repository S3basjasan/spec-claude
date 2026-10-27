import { toApiError } from '../../../shared/api/httpClient'
import { offboardingText } from './text'

const text = offboardingText.errors

export interface ProblemaDeSalida {
  /** Texto para mostrar; distingue la causa (política, vínculo, evidencia, cierre) de un error genérico. */
  mensaje: string
  /** Conflicto de edición o de estado: se explica y se ofrece actualizar, nunca se reenvía solo. */
  conflicto: boolean
  /** Errores por campo del servidor (422), con el nombre del campo del cuerpo. */
  campos: Record<string, string>
  /** El servidor respondió 404: el expediente o la ficha no existe o no es accesible. */
  noEncontrado: boolean
  /** Código de negocio del servidor, si lo hubo. */
  codigo?: string
  /** Bloqueos devueltos por un cierre rechazado. */
  bloqueos: string[]
  /** Incidencia de aplicación devuelta por un reintento. */
  incidencia?: { codigo: string; detalle: string | null }
}

const MENSAJES: Record<string, string> = {
  POLITICA_SIN_CONFIGURAR: text.policyNotConfigured,
  POLITICA_NO_DISPONIBLE: text.policyUnavailable,
  SIN_VINCULO_VIGENTE: text.noBond,
  SALIDA_YA_FORMALIZADA: text.alreadyFormalized,
  FECHA_EFECTIVA_INVALIDA: text.invalidDate,
  EVIDENCIA_REQUERIDA: text.evidenceRequired,
  CIERRE_NO_PROCEDE: text.closeBlocked,
  ENTREVISTA_SIN_RESOLVER: text.interviewUnresolved,
  ENTREVISTA_CERRADA: text.interviewClosed,
  ESTADO_NO_PERMITIDO: text.stateNotAllowed,
  APLICACION_NO_PROCEDE: text.notApplicable,
  SIN_PERMISO_DE_ENTREVISTAS: text.noInterviewPermission,
  CLAVE_DE_OPERACION_REUTILIZADA: text.keyReused,
  CLAVE_DE_OPERACION_REQUERIDA: text.keyRequired,
  SIN_FICHA_ASOCIADA: text.noFicha,
  FICHA_NO_ENCONTRADA: text.noRecord,
  EXPEDIENTE_NO_ENCONTRADO: text.notFound,
}

/** Traduce un error de la API de offboarding a lo que muestra la pantalla. */
export function problemaDeSalida(error: unknown): ProblemaDeSalida {
  const api = toApiError(error)
  const campos: Record<string, string> = {}
  for (const [campo, mensajes] of Object.entries(api.fieldErrors)) {
    if (mensajes[0]) campos[campo] = mensajes[0]
  }

  if (api.isNetworkError) return { mensaje: text.network, conflicto: false, campos, noEncontrado: false, bloqueos: [] }

  const codigo = api.code
  const conflicto = codigo === 'EXPEDIENTE_MODIFICADO' || (api.status === 409 && codigo === undefined)
  const incidencia = codigo === 'INCIDENCIA_DE_APLICACION' ? leerIncidencia(api.data) : undefined

  let mensaje = codigo !== undefined ? MENSAJES[codigo] : undefined
  if (conflicto) mensaje = text.conflict
  if (incidencia) mensaje = text.stillBlocked
  mensaje ??= api.status === 422 && Object.keys(campos).length > 0 ? Object.values(campos)[0] : text.unexpected

  const bloqueos = Array.isArray(api.data?.bloqueos) ? (api.data.bloqueos as unknown[]).filter((b): b is string => typeof b === 'string') : []

  return { mensaje, conflicto, campos, noEncontrado: api.status === 404, codigo, bloqueos, incidencia }
}

function leerIncidencia(datos: Record<string, unknown> | undefined): { codigo: string; detalle: string | null } | undefined {
  const bruto = datos?.incidencia
  if (bruto === null || typeof bruto !== 'object') return undefined
  const { codigo, detalle } = bruto as { codigo?: unknown; detalle?: unknown }
  return typeof codigo === 'string' ? { codigo, detalle: typeof detalle === 'string' ? detalle : null } : undefined
}
