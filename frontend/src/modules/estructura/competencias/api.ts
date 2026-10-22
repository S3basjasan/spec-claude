import { http } from '../../../shared/api/httpClient'

export type TipoCompetencia = 'Cardinal' | 'Especifica'
export type EstadoCompetencia = 'Activo' | 'Inactivo'
/** Lo que puede faltarle a una competencia heredada para estar completa. */
export type Faltante = 'grados' | 'comportamientos' | 'preguntas'

export const TIPOS_COMPETENCIA: readonly TipoCompetencia[] = ['Cardinal', 'Especifica']
/** Cantidad fija de textos de cada parte de una competencia. */
export const CANTIDAD = { grados: 4, comportamientosPorGrado: 4, preguntas: 4, categorias: 4 } as const

/** Elemento del listado: sin grados, comportamientos ni preguntas. */
export interface Competencia {
  id: number
  codigo: string
  nombre: string
  tipo: TipoCompetencia
  estado: EstadoCompetencia
  completa: boolean
}

export interface ValorDeEscala {
  orden: number
  valor: string
  etiqueta: string
}

/** Escala del catálogo con la que se nombran los cuatro grados (Letras, Números o Categorías). */
export interface Escala {
  id: number
  nombre: string
  activo: boolean
  valores: ValorDeEscala[]
}

export interface Grado {
  id: number
  orden: number
  etiqueta: string
  descripcion: string
  comportamientos: string[]
}

export interface Auditoria {
  created_by: string | null
  created_at: string | null
  updated_by: string | null
  updated_at: string | null
}

export interface CompetenciaDetalle {
  id: number
  codigo: string
  nombre: string
  descripcion: string
  tipo: TipoCompetencia
  estado: EstadoCompetencia
  version: number
  completa: boolean
  faltantes: Faltante[]
  escala: { tipo_grado_id: number; nombre: string } | null
  /** Nombres propios de la escala Categorías; null si la competencia no los tiene. */
  categorias: string[] | null
  grados: Grado[]
  preguntas: string[]
  /** Solo la recibe el Administrador. */
  auditoria?: Auditoria
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
  page?: number
  per_page?: number
}

/** Cuerpo de la creación: la competencia completa. */
export interface DatosCompetencia {
  nombre: string
  descripcion: string
  tipo: TipoCompetencia
  tipo_grado_id: number
  categorias?: string[]
  grados: string[]
  comportamientos: string[][]
  preguntas: string[]
}

/** Cuerpo de la edición de la competencia y sus grados (sin comportamientos ni preguntas). */
export type DatosSeccionCompetencia = Pick<DatosCompetencia, 'nombre' | 'descripcion' | 'tipo' | 'tipo_grado_id' | 'categorias' | 'grados'>

export async function listarCompetencias(filtros: FiltrosListado): Promise<PaginaCompetencias> {
  const params: Record<string, string | number> = {}
  if (filtros.q) params.q = filtros.q
  if (filtros.tipo) params.tipo = filtros.tipo
  if (filtros.page) params.page = filtros.page
  if (filtros.per_page) params.per_page = filtros.per_page
  const response = await http.get<PaginaCompetencias>('/api/competencias', { params })
  return response.data
}

export async function obtenerCompetencia(id: number): Promise<CompetenciaDetalle> {
  const response = await http.get<{ data: CompetenciaDetalle }>(`/api/competencias/${id}`)
  return response.data.data
}

export async function listarEscalas(): Promise<Escala[]> {
  const response = await http.get<Escala[]>('/api/tipo-grados')
  return response.data
}

export async function crearCompetencia(datos: DatosCompetencia): Promise<CompetenciaDetalle> {
  const response = await http.post<{ data: CompetenciaDetalle }>('/api/competencias', datos)
  return response.data.data
}

export async function actualizarCompetencia(id: number, datos: DatosSeccionCompetencia, version: number): Promise<CompetenciaDetalle> {
  const response = await http.put<{ data: CompetenciaDetalle }>(`/api/competencias/${id}`, { ...datos, version })
  return response.data.data
}

export async function actualizarComportamientos(id: number, comportamientos: string[][], version: number): Promise<CompetenciaDetalle> {
  const response = await http.put<{ data: CompetenciaDetalle }>(`/api/competencias/${id}/comportamientos`, { comportamientos, version })
  return response.data.data
}

export async function actualizarPreguntas(id: number, preguntas: string[], version: number): Promise<CompetenciaDetalle> {
  const response = await http.put<{ data: CompetenciaDetalle }>(`/api/competencias/${id}/preguntas`, { preguntas, version })
  return response.data.data
}

/** Cambia solo los nombres de los grados; no exige versión. */
export async function aplicarEscala(id: number, tipoGradoId: number): Promise<CompetenciaDetalle> {
  const response = await http.patch<{ data: CompetenciaDetalle }>(`/api/competencias/${id}/escala`, { tipo_grado_id: tipoGradoId })
  return response.data.data
}

/** Activa o desactiva una competencia; las competencias nunca se eliminan. */
export async function cambiarEstadoCompetencia(id: number, estado: EstadoCompetencia): Promise<CompetenciaDetalle> {
  const response = await http.patch<{ data: CompetenciaDetalle }>(`/api/competencias/${id}/estado`, { estado })
  return response.data.data
}
