import { z } from 'zod'
import { toApiError } from '../../../shared/api/httpClient'
import type { Categoria, DatosCategoria } from './api'
import { ICONO_IDS } from './iconos'
import { categoriasReconocimientoText } from './text'

export const LIMITE_NOMBRE = 100
export const LIMITE_DESCRIPCION = 500
export const LIMITE_INDICADOR = 250
export const MAX_INDICADORES = 5

const text = categoriasReconocimientoText.validation
const HTML = /<\/?[a-z!][^>]*>/i

/** Misma normalización que el servidor: sin distinguir mayúsculas, tildes ni espacios exteriores o repetidos. */
export function normalizarTexto(valor: string): string {
  return valor
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase()
}

/** Replica las reglas del servidor; la unicidad del nombre y la competencia activa las decide el servidor. */
export const categoriaSchema = z
  .object({
    nombre: z
      .string()
      .trim()
      .min(1, text.required)
      .min(3, text.nameLength)
      .max(LIMITE_NOMBRE, text.nameLength)
      .refine((valor) => !HTML.test(valor), text.html),
    descripcion: z
      .string()
      .trim()
      .min(1, text.required)
      .min(10, text.descriptionLength)
      .max(LIMITE_DESCRIPCION, text.descriptionLength)
      .refine((valor) => !HTML.test(valor), text.html),
    competencia_id: z.string().min(1, text.competencia),
    puntuacion_base: z
      .string()
      .trim()
      .min(1, text.required)
      .regex(/^\d+$/, text.pointsInteger)
      .refine((valor) => Number(valor) >= 1 && Number(valor) <= 1000, text.pointsRange),
    icono: z.string().refine((valor) => (ICONO_IDS as readonly string[]).includes(valor), text.icon),
    indicadores: z
      .array(
        z.object({
          texto: z
            .string()
            .trim()
            .min(1, text.required)
            .min(5, text.indicatorLength)
            .max(LIMITE_INDICADOR, text.indicatorLength)
            .refine((valor) => !HTML.test(valor), text.html),
        }),
      )
      .min(1, text.indicatorsRange)
      .max(MAX_INDICADORES, text.indicatorsRange),
  })
  .superRefine((valores, contexto) => {
    const vistos = new Set<string>()
    valores.indicadores.forEach((indicador, posicion) => {
      const clave = normalizarTexto(indicador.texto)
      if (clave === '') return
      if (vistos.has(clave)) {
        contexto.addIssue({ code: 'custom', message: text.indicatorRepeated, path: ['indicadores', posicion, 'texto'] })
      }
      vistos.add(clave)
    })
  })

export interface CategoriaFormValues {
  nombre: string
  descripcion: string
  competencia_id: string
  puntuacion_base: string
  icono: string
  indicadores: { texto: string }[]
}

export function valoresIniciales(categoria?: Categoria): CategoriaFormValues {
  if (!categoria) {
    return { nombre: '', descripcion: '', competencia_id: '', puntuacion_base: '', icono: '', indicadores: [{ texto: '' }] }
  }
  return {
    nombre: categoria.nombre,
    descripcion: categoria.descripcion,
    competencia_id: String(categoria.competencia?.id ?? ''),
    puntuacion_base: String(categoria.puntuacion_base),
    icono: categoria.icono,
    indicadores: (categoria.indicadores ?? []).map((indicador) => ({ texto: indicador.texto })),
  }
}

export function aDatosDeCategoria(valores: CategoriaFormValues): DatosCategoria {
  return {
    nombre: valores.nombre.trim(),
    descripcion: valores.descripcion.trim(),
    competencia_id: Number(valores.competencia_id),
    puntuacion_base: Number(valores.puntuacion_base),
    icono: valores.icono,
    indicadores: valores.indicadores.map((indicador) => indicador.texto.trim()),
  }
}

export type AvisoDeGuardado = 'conflicto' | 'noExiste' | 'red' | 'inesperado'

export interface ProblemaDeGuardado {
  /** Mensajes del servidor por campo del formulario (422). */
  campos: Partial<Record<'nombre' | 'descripcion' | 'competencia_id' | 'puntuacion_base' | 'icono' | 'indicadores', string>>
  /** Mensajes del servidor por posición de indicador (422). */
  indicadores: Record<number, string>
  /** Aviso general sobre el formulario; null si solo hay errores de campo. */
  aviso: AvisoDeGuardado | null
}

const CAMPOS = ['nombre', 'descripcion', 'competencia_id', 'puntuacion_base', 'icono', 'indicadores'] as const

/** Traduce un error de la API (422 por campo, 409, 404, red) a lo que muestra el formulario. */
export function problemaDeGuardado(error: unknown): ProblemaDeGuardado {
  const api = toApiError(error)
  const problema: ProblemaDeGuardado = { campos: {}, indicadores: {}, aviso: null }

  if (api.isNetworkError) return { ...problema, aviso: 'red' }
  if (api.status === 409) return { ...problema, aviso: 'conflicto' }
  if (api.status === 404) return { ...problema, aviso: 'noExiste' }

  if (api.status === 422) {
    for (const [clave, mensajes] of Object.entries(api.fieldErrors)) {
      const mensaje = mensajes[0]
      if (!mensaje) continue
      const coincidencia = /^indicadores\.(\d+)/.exec(clave)
      if (coincidencia) problema.indicadores[Number(coincidencia[1])] = mensaje
      else if ((CAMPOS as readonly string[]).includes(clave)) problema.campos[clave as (typeof CAMPOS)[number]] = mensaje
    }
    const hayErrores = Object.keys(problema.campos).length > 0 || Object.keys(problema.indicadores).length > 0
    if (hayErrores) return problema
  }

  return { ...problema, aviso: 'inesperado' }
}
