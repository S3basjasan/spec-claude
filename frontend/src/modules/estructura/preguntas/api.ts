import { http } from '../../../shared/api/httpClient'

export type TipoCompetencia = 'Cardinal' | 'Especifica'
export type EstadoCompetencia = 'Activo' | 'Inactivo'
/** Filtro de estado del listado (solo lo usa el Administrador); `Activo` es el valor por defecto. */
export type FiltroEstado = EstadoCompetencia | 'Todos'

/** Cantidad fija de preguntas de cada registro. */
export const CANTIDAD_PREGUNTAS = 4

/** Registro tal como aparece en el listado: datos de la competencia asociada, sin preguntas. */
export interface RegistroResumen {
  competencia_id: number
  codigo: string
  nombre: string
  descripcion: string
  tipo: TipoCompetencia
  estado: EstadoCompetencia
}

export interface RegistroDetalle extends RegistroResumen {
  version: number
  /** Exactamente cuatro textos, en el orden 1 a 4. */
  preguntas: string[]
}

export interface CompetenciaDisponible {
  competencia_id: number
  codigo: string
  nombre: string
  descripcion: string
  tipo: TipoCompetencia
}

export interface PaginaRegistros {
  data: RegistroResumen[]
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

export async function listarPreguntas(filtros: FiltrosListado): Promise<PaginaRegistros> {
  const params: Record<string, string | number> = {}
  if (filtros.q) params.q = filtros.q
  if (filtros.tipo) params.tipo = filtros.tipo
  if (filtros.estado) params.estado = filtros.estado
  if (filtros.page) params.page = filtros.page
  if (filtros.per_page) params.per_page = filtros.per_page
  const response = await http.get<PaginaRegistros>('/api/preguntas', { params })
  return response.data
}

export async function obtenerPreguntas(competenciaId: number): Promise<RegistroDetalle> {
  const response = await http.get<{ data: RegistroDetalle }>(`/api/preguntas/${competenciaId}`)
  return response.data.data
}

export async function listarCompetenciasDisponibles(): Promise<CompetenciaDisponible[]> {
  const response = await http.get<{ data: CompetenciaDisponible[] }>('/api/preguntas/competencias-disponibles')
  return response.data.data
}

export async function crearPreguntas(competenciaId: number, preguntas: string[]): Promise<RegistroDetalle> {
  const response = await http.post<{ data: RegistroDetalle }>('/api/preguntas', { competencia_id: competenciaId, preguntas })
  return response.data.data
}

export async function actualizarPreguntas(competenciaId: number, preguntas: string[], version: number): Promise<RegistroDetalle> {
  const response = await http.put<{ data: RegistroDetalle }>(`/api/preguntas/${competenciaId}`, {
    competencia_id: competenciaId,
    preguntas,
    version,
  })
  return response.data.data
}
