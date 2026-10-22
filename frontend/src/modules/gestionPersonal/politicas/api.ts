import { http } from '../../../shared/api/httpClient'

export type TipoPolitica = 'permisos' | 'ausencias' | 'licencias' | 'vacaciones' | 'ascensos' | 'traslados' | 'offboarding'

export const TIPOS: TipoPolitica[] = ['permisos', 'ausencias', 'licencias', 'vacaciones', 'ascensos', 'traslados', 'offboarding']

export type SituacionPolitica = 'SinConfigurar' | 'Vigente' | 'NoAdmiteSolicitudes'
export type FiltroSituacion = SituacionPolitica | 'ConProgramada'
export type EstadoVersion = 'Borrador' | 'Programada' | 'Vigente' | 'Sustituida' | 'Retirada'

export interface Tarea {
  orden?: number
  nombre: string
  descripcion: string | null
  exige_evidencia: boolean
}

/** Valores de parámetros: booleanos, enteros o la lista de tareas; el servidor valida cada clave contra el catálogo. */
export type ValorDeParametro = boolean | number | string | Tarea[]
export type Parametros = Record<string, ValorDeParametro>

export interface VersionCorta {
  id: number
  numero: number | null
  inicio_vigencia: string | null
  inicio_vigencia_local: string | null
}

export interface PoliticaResumen {
  tipo: TipoPolitica
  nombre: string
  categoria: 'Novedad' | 'Movimiento' | 'Offboarding'
  situacion: SituacionPolitica
  disponible: boolean | null
  version_vigente: VersionCorta | null
  version_programada: VersionCorta | null
  tiene_borrador: boolean
  borrador_id: number | null
}

export interface Version {
  id: number
  numero: number | null
  estado: EstadoVersion
  descripcion: string
  parametros: Parametros
  inicio_vigencia: string | null
  inicio_vigencia_local: string | null
  motivo_cambio: string | null
  revision: { revisor: string; observaciones: string | null; revision_en: string | null } | null
  publicada_por: number | null
  publicada_en: string | null
  retiro: { por: number | null; en: string | null; motivo: string | null } | null
  base_version_id: number | null
  version: number
  created_by: number
  created_at: string | null
  updated_by: number
  updated_at: string | null
}

export type TipoDeParametro = 'booleano' | 'entero' | 'tareas'

export interface DefinicionDeParametro {
  clave: string
  tipo: TipoDeParametro
  etiqueta: string
  ayuda: string
  obligatorio: boolean
  minimo?: number
}

export interface ReglaFija {
  clave: string
  texto: string
}

export interface Definicion {
  categoria: 'Novedad' | 'Movimiento' | 'Offboarding'
  parametros: DefinicionDeParametro[]
  reglas_fijas: ReglaFija[]
  limites: Record<string, number>
}

export interface PoliticaDetalle {
  resumen: PoliticaResumen
  definicion: Definicion
  vigente: Version | null
  programada: Version | null
  borrador: Version | null
  versiones: Version[]
}

export interface Diferencia {
  clave: string
  etiqueta: string
  anterior: unknown
  nuevo: unknown
}

export interface Comparacion {
  contra: Version | null
  diferencias: Diferencia[]
}

export interface Previsualizacion {
  condiciones: { numero: number; tipo: string; parametros: Parametros; reglas_fijas: ReglaFija[] }
  diferencias: Diferencia[]
  inicio_vigencia: string | null
  inicio_vigencia_local: string | null
  efecto: { momento: string | null; aplica_a: string; solicitudes_anteriores: string; sustituye: number | null }
  errores: Record<string, string[]>
}

export interface PoliticaAplicable {
  tipo: TipoPolitica
  situacion: SituacionPolitica
  numero: number | null
  inicio_vigencia: string | null
  parametros: Parametros | null
  reglas_fijas: ReglaFija[]
}

export type InicioDePublicacion = { modo: 'inmediato' } | { modo: 'programado'; fecha_hora_local: string }

export interface DatosDePublicacion {
  version: number
  motivo: string
  revision: { revisor: string; observaciones: string }
  inicio: InicioDePublicacion
}

export interface FiltrosPoliticas {
  tipo?: TipoPolitica
  situacion?: FiltroSituacion
}

const base = '/api/politicas-personal'

export async function listarPoliticas(filtros: FiltrosPoliticas, signal?: AbortSignal): Promise<PoliticaResumen[]> {
  const respuesta = await http.get<{ data: PoliticaResumen[] }>(base, { params: filtros, signal })
  return respuesta.data.data
}

export async function obtenerPolitica(tipo: TipoPolitica, signal?: AbortSignal): Promise<PoliticaDetalle> {
  const respuesta = await http.get<{ data: PoliticaDetalle }>(`${base}/${tipo}`, { signal })
  return respuesta.data.data
}

export async function crearBorrador(tipo: TipoPolitica, baseVersionId?: number): Promise<Version> {
  const respuesta = await http.post<{ data: Version }>(`${base}/${tipo}/borrador`, baseVersionId === undefined ? {} : { base_version_id: baseVersionId })
  return respuesta.data.data
}

export interface CambiosDeBorrador {
  version: number
  descripcion: string
  parametros: Parametros
}

export async function editarBorrador(tipo: TipoPolitica, id: number, cambios: CambiosDeBorrador): Promise<Version> {
  const respuesta = await http.patch<{ data: Version }>(`${base}/${tipo}/borrador/${id}`, cambios)
  return respuesta.data.data
}

export async function descartarBorrador(tipo: TipoPolitica, id: number, version: number): Promise<void> {
  await http.post(`${base}/${tipo}/borrador/${id}/descarte`, { version })
}

export async function previsualizarPublicacion(tipo: TipoPolitica, id: number, inicio: InicioDePublicacion | null): Promise<Previsualizacion> {
  const respuesta = await http.post<{ data: Previsualizacion }>(`${base}/${tipo}/borrador/${id}/previsualizacion`, inicio === null ? {} : { inicio })
  return respuesta.data.data
}

export async function publicarVersion(tipo: TipoPolitica, id: number, datos: DatosDePublicacion, claveDeOperacion: string): Promise<Version> {
  const respuesta = await http.post<{ data: Version }>(`${base}/${tipo}/borrador/${id}/publicacion`, datos, { headers: { 'Idempotency-Key': claveDeOperacion } })
  return respuesta.data.data
}

export async function retirarVersion(tipo: TipoPolitica, id: number, justificacion: string, claveDeOperacion: string): Promise<Version> {
  const respuesta = await http.post<{ data: Version }>(`${base}/${tipo}/versiones/${id}/retiro`, { justificacion }, { headers: { 'Idempotency-Key': claveDeOperacion } })
  return respuesta.data.data
}

export async function compararVersion(tipo: TipoPolitica, id: number, signal?: AbortSignal): Promise<Comparacion> {
  const respuesta = await http.get<{ data: Comparacion }>(`${base}/${tipo}/versiones/${id}/comparacion`, { signal })
  return respuesta.data.data
}

export async function obtenerPoliticaAplicable(tipo: TipoPolitica, signal?: AbortSignal): Promise<PoliticaAplicable> {
  const respuesta = await http.get<{ data: PoliticaAplicable }>(`${base}/${tipo}/aplicable`, { signal })
  return respuesta.data.data
}
