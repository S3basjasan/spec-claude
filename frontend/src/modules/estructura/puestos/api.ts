import { http } from '../../../shared/api/httpClient'

export type EstadoPuesto = 'Activo' | 'Inactivo'
/** Filtro de estado del listado (solo lo usa el Administrador); `Activo` es el valor por defecto. */
export type FiltroEstado = EstadoPuesto | 'Todos'
export type TipoFuncion = 'Esencial' | 'Complementaria'
/** Valores tal como los guarda el servidor (sin tildes); la interfaz los muestra con tilde. */
export type TipoRequisito = 'Formacion' | 'Experiencia' | 'Capacitacion'
export type TipoCompetencia = 'Cardinal' | 'Especifica'
export type EstadoCompetencia = 'Activo' | 'Inactivo'

/** Máximo de elementos de cada lista de un puesto (funciones, requisitos y competencias). */
export const MAX_ELEMENTOS = 20

/** Puesto tal como aparece en el listado: sin misión, funciones, requisitos ni competencias. */
export interface PuestoResumen {
  id: number
  codigo: string
  nombre: string
  descripcion: string
  estado: EstadoPuesto
}

export interface FuncionDetalle {
  orden: number
  descripcion: string
  tipo: TipoFuncion
}

export interface RequisitoDetalle {
  tipo: TipoRequisito
  descripcion: string
}

/** Competencia asignada: código, nombre, tipo y estado son los actuales del diccionario (solo lectura). */
export interface CompetenciaAsignada {
  competencia_id: number
  codigo: string
  nombre: string
  tipo: TipoCompetencia
  estado: EstadoCompetencia
  grado_requerido: string
}

export interface PuestoDetalle extends PuestoResumen {
  mision: string
  version: number
  funciones: FuncionDetalle[]
  requisitos: RequisitoDetalle[]
  competencias: CompetenciaAsignada[]
}

export interface GradoDisponible {
  grado: string
  descripcion: string
}

export interface CompetenciaDisponible {
  competencia_id: number
  codigo: string
  nombre: string
  tipo: TipoCompetencia
  grados: GradoDisponible[]
}

export interface PaginaPuestos {
  data: PuestoResumen[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
    from: number | null
    to: number | null
  }
}

export interface FiltrosListado {
  q?: string
  /** Solo se envía para el Administrador; para el Usuario el backend siempre devuelve los activos. */
  estado?: FiltroEstado
  page?: number
  per_page?: number
}

/** Contenido editable de un puesto, tal como lo espera la API (el orden de las funciones es el del arreglo). */
export interface DatosPuesto {
  nombre: string
  descripcion: string
  mision: string
  funciones: { descripcion: string; tipo: TipoFuncion }[]
  requisitos: { tipo: TipoRequisito; descripcion: string }[]
  competencias: { competencia_id: number; grado_requerido: string }[]
}

export async function listarPuestos(filtros: FiltrosListado): Promise<PaginaPuestos> {
  const params: Record<string, string | number> = {}
  if (filtros.q) params.q = filtros.q
  if (filtros.estado) params.estado = filtros.estado
  if (filtros.page) params.page = filtros.page
  if (filtros.per_page) params.per_page = filtros.per_page
  const response = await http.get<PaginaPuestos>('/api/puestos', { params })
  return response.data
}

export async function obtenerPuesto(id: number): Promise<PuestoDetalle> {
  const response = await http.get<{ data: PuestoDetalle }>(`/api/puestos/${id}`)
  return response.data.data
}

export async function listarCompetenciasDisponibles(): Promise<CompetenciaDisponible[]> {
  const response = await http.get<{ data: CompetenciaDisponible[] }>('/api/puestos/competencias-disponibles')
  return response.data.data
}

export async function crearPuesto(datos: DatosPuesto): Promise<PuestoDetalle> {
  const response = await http.post<{ data: PuestoDetalle }>('/api/puestos', datos)
  return response.data.data
}

export async function actualizarPuesto(id: number, datos: DatosPuesto, version: number): Promise<PuestoDetalle> {
  const response = await http.put<{ data: PuestoDetalle }>(`/api/puestos/${id}`, { ...datos, version })
  return response.data.data
}

export async function cambiarEstadoPuesto(id: number, estado: EstadoPuesto): Promise<PuestoDetalle> {
  const response = await http.patch<{ data: PuestoDetalle }>(`/api/puestos/${id}/estado`, { estado })
  return response.data.data
}
