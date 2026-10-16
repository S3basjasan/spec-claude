import { z } from 'zod'
import { toApiError } from '../../../shared/api/httpClient'
import type { DatosExperiencia, DatosFormacion, DatosMovilidad, EstadoFormacion, EstadoMovilidad, Experiencia, Formacion, Movilidad } from './api'
import { desarrolloText } from './text'

export const LIMITES = {
  nivel: 100,
  tituloPrograma: 200,
  institucion: 200,
  organizacion: 200,
  cargo: 200,
  funciones: 2000,
  observaciones: 2000,
} as const

const text = desarrolloText.validation
const PATRON_FECHA = /^\d{4}-\d{2}-\d{2}$/

function esFechaReal(valor: string): boolean {
  if (!PATRON_FECHA.test(valor)) return false
  const [anio, mes, dia] = valor.split('-').map(Number)
  const fecha = new Date(Date.UTC(anio, mes - 1, dia))
  return fecha.getUTCFullYear() === anio && fecha.getUTCMonth() === mes - 1 && fecha.getUTCDate() === dia
}

const obligatorio = (max: number) => z.string().trim().min(1, text.required).max(max, text.tooLong(max))
const opcional = (max: number) => z.string().trim().max(max, text.tooLong(max))

type Problema = (campo: string, message: string) => void

/** Reglas comunes de fechas; `hoy` llega del servidor (zona del tenant), no del reloj del navegador. */
function validarFechas(valores: { fecha_inicio: string; fecha_finalizacion: string }, hoy: string, finNoFutura: boolean, problema: Problema) {
  const { fecha_inicio: inicio, fecha_finalizacion: fin } = valores
  const inicioOk = inicio !== '' && esFechaReal(inicio)
  const finOk = fin !== '' && esFechaReal(fin)

  if (inicio !== '') {
    if (!inicioOk) problema('fecha_inicio', text.invalidDate)
    else if (inicio > hoy) problema('fecha_inicio', text.futureStart)
  }
  if (fin !== '') {
    if (!finOk) problema('fecha_finalizacion', text.invalidDate)
    else if (finNoFutura && fin > hoy) problema('fecha_finalizacion', text.futureEnd)
  }
  if (inicioOk && finOk && fin < inicio) problema('fecha_finalizacion', text.endBeforeStart)
}

/** Replica las reglas de ReglasDeDesarrollo del backend para formación académica. */
export function formacionSchema(hoy: string) {
  return z
    .object({
      nivel: obligatorio(LIMITES.nivel),
      titulo_programa: obligatorio(LIMITES.tituloPrograma),
      institucion: obligatorio(LIMITES.institucion),
      estado: z.enum(['EnCurso', 'Completada', 'Incompleta'], { error: text.invalidStatus }),
      fecha_inicio: z.string().trim(),
      fecha_finalizacion: z.string().trim(),
      observaciones: opcional(LIMITES.observaciones),
    })
    .superRefine((valores, contexto) =>
      validarFechas(valores, hoy, false, (campo, message) => contexto.addIssue({ code: 'custom', path: [campo], message })),
    )
}

export function experienciaSchema(hoy: string) {
  return z
    .object({
      organizacion: obligatorio(LIMITES.organizacion),
      cargo: obligatorio(LIMITES.cargo),
      fecha_inicio: z.string().trim().min(1, text.required),
      fecha_finalizacion: z.string().trim(),
      funciones: opcional(LIMITES.funciones),
      observaciones: opcional(LIMITES.observaciones),
    })
    .superRefine((valores, contexto) =>
      validarFechas(valores, hoy, true, (campo, message) => contexto.addIssue({ code: 'custom', path: [campo], message })),
    )
}

export const movilidadSchema = z.object({
  estado: z.enum(['Disponible', 'NoDisponible', 'NoInformada']),
  observaciones: opcional(LIMITES.observaciones),
})

/** Valores del formulario: todo texto; el estado de formación puede estar sin elegir (''). */
export interface FormacionFormValues {
  nivel: string
  titulo_programa: string
  institucion: string
  estado: EstadoFormacion | ''
  fecha_inicio: string
  fecha_finalizacion: string
  observaciones: string
}

export interface ExperienciaFormValues {
  organizacion: string
  cargo: string
  fecha_inicio: string
  fecha_finalizacion: string
  funciones: string
  observaciones: string
}

export interface MovilidadFormValues {
  estado: EstadoMovilidad
  observaciones: string
}

const vacioANulo = (valor: string): string | null => (valor.trim() === '' ? null : valor.trim())

export function valoresVaciosFormacion(): FormacionFormValues {
  return { nivel: '', titulo_programa: '', institucion: '', estado: '', fecha_inicio: '', fecha_finalizacion: '', observaciones: '' }
}

export function valoresDeFormacion(fila: Formacion): FormacionFormValues {
  return {
    nivel: fila.nivel,
    titulo_programa: fila.titulo_programa,
    institucion: fila.institucion,
    estado: fila.estado,
    fecha_inicio: fila.fecha_inicio ?? '',
    fecha_finalizacion: fila.fecha_finalizacion ?? '',
    observaciones: fila.observaciones ?? '',
  }
}

export function datosDeFormacion(valores: FormacionFormValues): DatosFormacion {
  return {
    nivel: valores.nivel.trim(),
    titulo_programa: valores.titulo_programa.trim(),
    institucion: valores.institucion.trim(),
    estado: valores.estado as EstadoFormacion,
    fecha_inicio: vacioANulo(valores.fecha_inicio),
    fecha_finalizacion: vacioANulo(valores.fecha_finalizacion),
    observaciones: vacioANulo(valores.observaciones),
  }
}

export function valoresVaciosExperiencia(): ExperienciaFormValues {
  return { organizacion: '', cargo: '', fecha_inicio: '', fecha_finalizacion: '', funciones: '', observaciones: '' }
}

export function valoresDeExperiencia(fila: Experiencia): ExperienciaFormValues {
  return {
    organizacion: fila.organizacion,
    cargo: fila.cargo,
    fecha_inicio: fila.fecha_inicio,
    fecha_finalizacion: fila.fecha_finalizacion ?? '',
    funciones: fila.funciones ?? '',
    observaciones: fila.observaciones ?? '',
  }
}

export function datosDeExperiencia(valores: ExperienciaFormValues): DatosExperiencia {
  return {
    organizacion: valores.organizacion.trim(),
    cargo: valores.cargo.trim(),
    fecha_inicio: valores.fecha_inicio.trim(),
    fecha_finalizacion: vacioANulo(valores.fecha_finalizacion),
    funciones: vacioANulo(valores.funciones),
    observaciones: vacioANulo(valores.observaciones),
  }
}

export function valoresDeMovilidad(bloque: Movilidad): MovilidadFormValues {
  return { estado: bloque.estado, observaciones: bloque.observaciones ?? '' }
}

export function datosDeMovilidad(valores: MovilidadFormValues): DatosMovilidad {
  return { estado: valores.estado, observaciones: vacioANulo(valores.observaciones) }
}

export interface ProblemaDeGuardado {
  /** Primer mensaje del servidor por campo (422). */
  campos: Record<string, string>
  /** Aviso general que se muestra sobre el formulario; null si solo hay errores por campo. */
  aviso: 'conflicto' | 'noExiste' | 'deshabilitada' | 'red' | 'inesperado' | null
}

/** Traduce un error de la API de desarrollo (422 por campo, 409, 404, 503, red) a lo que muestra el diálogo. */
export function problemaDeGuardado(error: unknown, camposConocidos: readonly string[]): ProblemaDeGuardado {
  const api = toApiError(error)
  const campos: Record<string, string> = {}
  for (const [campo, mensajes] of Object.entries(api.fieldErrors)) {
    if (camposConocidos.includes(campo) && mensajes[0]) campos[campo] = mensajes[0]
  }

  if (api.isNetworkError) return { campos, aviso: 'red' }
  if (api.status === 409) return { campos, aviso: 'conflicto' }
  if (api.status === 404) return { campos, aviso: 'noExiste' }
  if (api.code === 'ESCRITURA_DESHABILITADA') return { campos, aviso: 'deshabilitada' }
  if (api.status === 422 && Object.keys(campos).length > 0) return { campos, aviso: null }
  return { campos, aviso: 'inesperado' }
}
