import type { Categoria, PaginaCategorias } from '../modules/gestionPersonal/categorias/api'

export function categoria(cambios: Partial<Categoria> = {}): Categoria {
  return {
    id: 1,
    nombre: 'Contratos',
    estado: 'Activa',
    version: 1,
    created_by: 1,
    updated_by: 1,
    created_at: '2026-10-16T14:00:00Z',
    updated_at: '2026-10-16T14:00:00Z',
    ...cambios,
  }
}

export function paginaDeCategorias(items: Categoria[], meta: Partial<PaginaCategorias['meta']> = {}): PaginaCategorias {
  return {
    data: items,
    meta: {
      current_page: 1,
      per_page: 20,
      total: items.length,
      last_page: 1,
      from: items.length ? 1 : null,
      to: items.length || null,
      ...meta,
    },
  }
}
