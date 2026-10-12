import { http } from '../../../shared/api/httpClient'

export type TipoCompetencia = 'Cardinal' | 'Especifica'
export type EstadoCompetencia = 'Activo' | 'Inactivo'
/** Filtro de estado del listado (solo lo usa el Administrador); `Activo` es el valor por defecto. */
export type FiltroEstado = EstadoCompetencia | 'Todos'
export type LetraGrado = 'A' | 'B' | 'C' | 'D'

export const LETRAS_GRADO: readonly LetraGrado[] = ['A', 'B', 'C', 'D']
export const TIPOS_COMPETENCIA: readonly TipoCompetencia[] = ['Cardinal', 'Especifica']

export interface Competencia {
  id: number
  codigo: string
  nombre: string
  descripcion: string
  tipo: TipoCompetencia
  estado: EstadoCompetencia
}

export interface Grado {
  grado: LetraGrado
  descripcion: string
}

export interface CompetenciaDetalle extends Competencia {
  version: number
  grados: Grado[]
}

export interface PaginaCompetencias {
  data: Competencia[]
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
  tipo?: TipoCompetencia
  /** Solo se envía para el Administrador; para el Usuario el backend siempre devuelve las activas. */
  estado?: FiltroEstado
  page?: number
  per_page?: number
}

export interface DatosCompetencia {
  nombre: string
  descripcion: string
  tipo: TipoCompetencia
  grados: Record<LetraGrado, string>
}

export async function listarCompetencias(filtros: FiltrosListado): Promise<PaginaCompetencias> {
  const params: Record<string, string | number> = {}
  if (filtros.q) params.q = filtros.q
  if (filtros.tipo) params.tipo = filtros.tipo
  if (filtros.estado) params.estado = filtros.estado
  if (filtros.page) params.page = filtros.page
  if (filtros.per_page) params.per_page = filtros.per_page
  const response = await http.get<PaginaCompetencias>('/api/competencias', { params })
  return response.data
}

export async function obtenerCompetencia(id: number): Promise<CompetenciaDetalle> {
  const response = await http.get<{ data: CompetenciaDetalle }>(`/api/competencias/${id}`)
  return response.data.data
}

export async function crearCompetencia(datos: DatosCompetencia): Promise<CompetenciaDetalle> {
  const response = await http.post<{ data: CompetenciaDetalle }>('/api/competencias', datos)
  return response.data.data
}

export async function actualizarCompetencia(id: number, datos: DatosCompetencia, version: number): Promise<CompetenciaDetalle> {
  const response = await http.put<{ data: CompetenciaDetalle }>(`/api/competencias/${id}`, { ...datos, version })
  return response.data.data
}

/** Activa o desactiva una competencia; las competencias nunca se eliminan. */
export async function cambiarEstadoCompetencia(id: number, estado: EstadoCompetencia): Promise<CompetenciaDetalle> {
  const response = await http.patch<{ data: CompetenciaDetalle }>(`/api/competencias/${id}/estado`, { estado })
  return response.data.data
}
