import { http } from '../../../shared/api/httpClient'

export type TipoCompetencia = 'Cardinal' | 'Especifica'
export type EstadoCompetencia = 'Activo' | 'Inactivo'
/** Filtro de estado del listado (solo lo usa el Administrador); `Activo` es el valor por defecto. */
export type FiltroEstado = EstadoCompetencia | 'Todos'
export type LetraGrado = 'A' | 'B' | 'C' | 'D'

export const LETRAS_GRADO: readonly LetraGrado[] = ['A', 'B', 'C', 'D']

/** Registro tal como aparece en el listado: datos de la competencia asociada, sin comportamientos. */
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
  comportamientos: Record<LetraGrado, string[]>
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

export type ComportamientosPorGrado = Record<LetraGrado, string[]>

export async function listarComportamientos(filtros: FiltrosListado): Promise<PaginaRegistros> {
  const params: Record<string, string | number> = {}
  if (filtros.q) params.q = filtros.q
  if (filtros.tipo) params.tipo = filtros.tipo
  if (filtros.estado) params.estado = filtros.estado
  if (filtros.page) params.page = filtros.page
  if (filtros.per_page) params.per_page = filtros.per_page
  const response = await http.get<PaginaRegistros>('/api/comportamientos', { params })
  return response.data
}

export async function obtenerComportamientos(competenciaId: number): Promise<RegistroDetalle> {
  const response = await http.get<{ data: RegistroDetalle }>(`/api/comportamientos/${competenciaId}`)
  return response.data.data
}

export async function listarCompetenciasDisponibles(): Promise<CompetenciaDisponible[]> {
  const response = await http.get<{ data: CompetenciaDisponible[] }>('/api/comportamientos/competencias-disponibles')
  return response.data.data
}

export async function crearComportamientos(competenciaId: number, comportamientos: ComportamientosPorGrado): Promise<RegistroDetalle> {
  const response = await http.post<{ data: RegistroDetalle }>('/api/comportamientos', {
    competencia_id: competenciaId,
    comportamientos,
  })
  return response.data.data
}

export async function actualizarComportamientos(
  competenciaId: number,
  comportamientos: ComportamientosPorGrado,
  version: number,
): Promise<RegistroDetalle> {
  const response = await http.put<{ data: RegistroDetalle }>(`/api/comportamientos/${competenciaId}`, {
    competencia_id: competenciaId,
    comportamientos,
    version,
  })
  return response.data.data
}
