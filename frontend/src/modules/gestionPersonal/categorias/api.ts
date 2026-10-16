import { http } from '../../../shared/api/httpClient'

export type EstadoCategoria = 'Activa' | 'Inactiva'
export type FiltroEstadoCategoria = EstadoCategoria | 'Todas'

export const POR_PAGINA = 20
export const ESTADO_INICIAL: FiltroEstadoCategoria = 'Activa'

export interface Categoria {
  id: number
  nombre: string
  estado: EstadoCategoria
  version: number
  created_by: number
  updated_by: number
  created_at: string
  updated_at: string
}

export interface PaginaCategorias {
  data: Categoria[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
    from: number | null
    to: number | null
  }
}

export interface FiltrosCategorias {
  q?: string
  estado?: FiltroEstadoCategoria
  page?: number
  per_page?: number
}

const BASE = '/api/categorias-documentales'

export async function listarCategorias(filtros: FiltrosCategorias, signal?: AbortSignal): Promise<PaginaCategorias> {
  const params: Record<string, string | number> = {
    estado: filtros.estado ?? ESTADO_INICIAL,
    per_page: filtros.per_page ?? POR_PAGINA,
  }
  if (filtros.q) params.q = filtros.q
  if (filtros.page) params.page = filtros.page
  const response = await http.get<PaginaCategorias>(BASE, { params, signal })
  return response.data
}

export async function crearCategoria(nombre: string): Promise<Categoria> {
  const response = await http.post<{ data: Categoria }>(BASE, { nombre })
  return response.data.data
}

export async function editarCategoria(id: number, nombre: string, version: number): Promise<Categoria> {
  const response = await http.patch<{ data: Categoria }>(`${BASE}/${id}`, { nombre, version })
  return response.data.data
}

export async function cambiarEstadoCategoria(id: number, estado: EstadoCategoria, version: number): Promise<Categoria> {
  const response = await http.patch<{ data: Categoria }>(`${BASE}/${id}/estado`, { estado, version })
  return response.data.data
}
