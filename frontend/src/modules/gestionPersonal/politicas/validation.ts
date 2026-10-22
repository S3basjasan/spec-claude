import { z } from 'zod'
import { toApiError } from '../../../shared/api/httpClient'
import type { Definicion, DefinicionDeParametro, Parametros, Tarea, ValorDeParametro } from './api'
import { politicasText } from './text'

/** Máximos y límites del servidor (`CatalogoDePoliticas`): el servidor es la autoridad y los repite. */
export const LIMITES = {
  descripcion: 1000,
  motivo: 500,
  justificacionRetiro: 500,
  revisor: 150,
  observacionesRevision: 1000,
  tareaNombre: 150,
  tareaDescripcion: 500,
  maxTareas: 30,
} as const

const text = politicasText.validation

/** Valores en edición: una clave ausente significa «sin definir» (no se asume ningún valor de negocio). */
export type ParametrosEnEdicion = Record<string, ValorDeParametro | undefined>

const sinTildes = (valor: string) => valor.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()

/** Texto de un instante local `AAAA-MM-DDTHH:mm` como `DD/MM/AAAA HH:mm`. */
export function formatearInstanteLocal(local: string | null): string {
  if (local === null) return politicasText.none
  const coincidencia = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(local)
  return coincidencia ? `${coincidencia[3]}/${coincidencia[2]}/${coincidencia[1]} ${coincidencia[4]}:${coincidencia[5]}` : local
}

/** Valor de una diferencia en lenguaje de la interfaz (Sí/No, días calendario, resumen de tareas, sin definir). */
export function formatearValor(valor: unknown): string {
  if (valor === null || valor === undefined || valor === '') return politicasText.notDefined
  if (typeof valor === 'boolean') return valor ? politicasText.yes : politicasText.no
  if (typeof valor === 'number') return `${valor} ${politicasText.params.daysUnit}`
  if (Array.isArray(valor)) {
    const nombres = valor.map((t) => (typeof t === 'object' && t !== null && 'nombre' in t ? String((t as { nombre: unknown }).nombre) : String(t)))
    return nombres.length === 0 ? politicasText.params.tasks.empty : `${nombres.length} · ${nombres.join(', ')}`
  }
  return String(valor)
}

export function descripcionError(descripcion: string): string | undefined {
  return descripcion.trim().length > LIMITES.descripcion ? text.tooLong(LIMITES.descripcion) : undefined
}

function errorDeEntero(definicion: DefinicionDeParametro, valor: ValorDeParametro | undefined): string | undefined {
  if (valor === undefined || valor === '') return undefined
  const numero = typeof valor === 'number' ? valor : /^-?\d+$/.test(String(valor).trim()) ? Number(valor) : Number.NaN
  if (!Number.isInteger(numero)) return text.integer
  if (numero < (definicion.minimo ?? 0)) return text.min(definicion.minimo ?? 0)
  return undefined
}

/** Errores de los parámetros enviados (los obligatorios se exigen al publicar); claves `parametros.<clave>` como el servidor. */
export function erroresDeParametros(definicion: Definicion, parametros: ParametrosEnEdicion): Record<string, string> {
  const errores: Record<string, string> = {}

  for (const definido of definicion.parametros) {
    const valor = parametros[definido.clave]

    if (definido.tipo === 'entero') {
      const error = errorDeEntero(definido, valor)
      if (error) errores[`parametros.${definido.clave}`] = error
    }

    if (definido.tipo === 'tareas' && Array.isArray(valor)) {
      if (valor.length > LIMITES.maxTareas) errores[`parametros.${definido.clave}`] = text.tooManyTasks(LIMITES.maxTareas)
      const vistos = new Set<string>()
      valor.forEach((tarea, i) => {
        const nombre = tarea.nombre.trim()
        const base = `parametros.${definido.clave}.${i}`
        if (nombre === '') errores[`${base}.nombre`] = text.taskNameRequired
        else if (nombre.length > LIMITES.tareaNombre) errores[`${base}.nombre`] = text.tooLong(LIMITES.tareaNombre)
        else if (vistos.has(sinTildes(nombre))) errores[`${base}.nombre`] = text.taskDuplicate
        vistos.add(sinTildes(nombre))
        if ((tarea.descripcion ?? '').trim().length > LIMITES.tareaDescripcion) errores[`${base}.descripcion`] = text.tooLong(LIMITES.tareaDescripcion)
      })
    }
  }

  return errores
}

/** Parámetros listos para enviar: enteros escritos como texto pasan a número, vacíos se omiten y las tareas se recortan. */
export function parametrosParaEnviar(definicion: Definicion, parametros: ParametrosEnEdicion): Parametros {
  const salida: Parametros = {}

  for (const definido of definicion.parametros) {
    const valor = parametros[definido.clave]
    if (valor === undefined || valor === '') continue

    if (definido.tipo === 'entero') salida[definido.clave] = typeof valor === 'number' ? valor : Number(String(valor).trim())
    else if (definido.tipo === 'tareas' && Array.isArray(valor)) {
      salida[definido.clave] = valor.map((tarea): Tarea => ({
        nombre: tarea.nombre.trim(),
        descripcion: (tarea.descripcion ?? '').trim() === '' ? null : (tarea.descripcion ?? '').trim(),
        exige_evidencia: tarea.exige_evidencia,
      }))
    } else salida[definido.clave] = valor
  }

  return salida
}

/** Parámetros del servidor como valores de edición. */
export function parametrosEnEdicion(parametros: Parametros): ParametrosEnEdicion {
  return { ...parametros }
}

/** Esquema de publicación: motivo y revisión obligatorios; el inicio programado debe ser una fecha y hora futuras. */
export const publicacionSchema = z
  .object({
    motivo: z.string().trim().min(1, text.required).max(LIMITES.motivo, text.tooLong(LIMITES.motivo)),
    revisor: z.string().trim().min(1, text.required).max(LIMITES.revisor, text.tooLong(LIMITES.revisor)),
    observaciones: z.string().trim().max(LIMITES.observacionesRevision, text.tooLong(LIMITES.observacionesRevision)),
    modo: z.enum(['inmediato', 'programado']),
    fecha_hora_local: z.string(),
  })
  .superRefine((valores, contexto) => {
    if (valores.modo !== 'programado') return
    if (valores.fecha_hora_local.trim() === '') {
      contexto.addIssue({ code: 'custom', path: ['fecha_hora_local'], message: text.startRequired })
    }
  })

export interface PublicacionFormValues {
  motivo: string
  revisor: string
  observaciones: string
  modo: 'inmediato' | 'programado'
  fecha_hora_local: string
}

export const retiroSchema = z.object({
  justificacion: z.string().trim().min(1, text.required).max(LIMITES.justificacionRetiro, text.tooLong(LIMITES.justificacionRetiro)),
})

export interface RetiroFormValues {
  justificacion: string
}

export type AvisoDeGuardado = 'conflicto' | 'programada' | 'noExiste' | 'deshabilitada' | 'iniciada' | 'noRetirable' | 'red' | 'inesperado' | null

export interface ProblemaDeGuardado {
  /** Primer mensaje del servidor por campo (422), con las claves del servidor. */
  campos: Record<string, string>
  aviso: AvisoDeGuardado
}

/** Traduce un error de la API (422 por campo, 409, 404, 503, red) a lo que muestra la pantalla. */
export function problemaDeGuardado(error: unknown): ProblemaDeGuardado {
  const api = toApiError(error)
  const campos: Record<string, string> = {}
  for (const [clave, mensajes] of Object.entries(api.fieldErrors)) {
    if (mensajes[0] !== undefined) campos[clave] = mensajes[0]
  }

  if (api.isNetworkError) return { campos, aviso: 'red' }
  if (api.code === 'BORRADOR_MODIFICADO' || api.code === 'BORRADOR_EXISTENTE') return { campos, aviso: 'conflicto' }
  if (api.code === 'PROGRAMADA_EXISTENTE') return { campos, aviso: 'programada' }
  if (api.code === 'VIGENCIA_INICIADA') return { campos, aviso: 'iniciada' }
  if (api.code === 'VERSION_NO_RETIRABLE') return { campos, aviso: 'noRetirable' }
  if (api.code === 'CLAVE_DE_OPERACION_REUTILIZADA') return { campos, aviso: 'conflicto' }
  if (api.status === 404) return { campos, aviso: 'noExiste' }
  if (api.code === 'ESCRITURA_DESHABILITADA') return { campos, aviso: 'deshabilitada' }
  if (api.status === 422 && Object.keys(campos).length > 0) return { campos, aviso: null }
  return { campos, aviso: 'inesperado' }
}

/** Clave de operación para una publicación o un retiro: se reutiliza al reintentar el mismo contenido. */
export function nuevaClaveDeOperacion(): string {
  return crypto.randomUUID()
}
