import { http } from '../../../shared/api/httpClient'

export const CLAVES = [
  'puntos_por_colaborador',
  'plazo_anulacion_dias',
  'umbral_concentracion',
  'plazo_max_desafio_dias',
  'umbral_clima_unidad',
  'sensibilidad_radar',
] as const

export type ClaveParametro = (typeof CLAVES)[number]

export interface ValorProgramado {
  valor: number
  vigente_desde: string
}

export interface UltimoCambio {
  por: string | null
  en: string
  /** Nulo en el primer cambio de la clave: no hay valor anterior guardado. */
  valor_anterior: number | null
  valor_nuevo: number
}

export interface Parametro {
  clave: ClaveParametro
  valor: number
  valor_programado: ValorProgramado | null
  minimo: number
  maximo: number
  por_defecto: number
  /** Id de la última fila de la clave (0 si nunca cambió): se devuelve tal cual al guardar. */
  version: number
  ultimo_cambio: UltimoCambio | null
}

export interface CambioDeParametro {
  clave: ClaveParametro
  valor: number
  version: number
}

const BASE = '/api/compensaciones/parametros'

export async function listarParametros(signal?: AbortSignal): Promise<Parametro[]> {
  const response = await http.get<{ data: Parametro[] }>(BASE, { signal })
  return response.data.data
}

export async function guardarParametros(cambios: CambioDeParametro[]): Promise<Parametro[]> {
  const response = await http.put<{ data: Parametro[] }>(BASE, { cambios })
  return response.data.data
}
