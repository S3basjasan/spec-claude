import type { Categoria, OpcionDeCompetencia, PaginaCategorias } from '../modules/compensaciones/categorias/api'

export function categoriaReconocimiento(cambios: Partial<Categoria> = {}): Categoria {
  return {
    id: 1,
    codigo: 'CAT-001',
    nombre: 'Trabajo en equipo',
    descripcion: 'Colabora de forma activa con sus compañeros.',
    competencia: { id: 10, codigo: 'COMP-0010', nombre: 'Colaboración', estado: 'Activo' },
    competencia_activa: true,
    seleccionable: true,
    puntuacion_base: 100,
    icono: 'equipo',
    estado: 'Activo',
    indicadores: [
      { orden: 1, texto: 'Comparte información con su equipo' },
      { orden: 2, texto: 'Ayuda a quien lo necesita' },
    ],
    updated_at: '2026-10-16T14:00:00.123456Z',
    created_by: 1,
    updated_by: 2,
    created_at: '2026-10-15T09:00:00.000000Z',
    ...cambios,
  }
}

export function paginaDeCategoriasReconocimiento(items: Categoria[], meta: Partial<PaginaCategorias['meta']> = {}): PaginaCategorias {
  return {
    data: items,
    meta: {
      page: 1,
      per_page: 15,
      total: items.length,
      last_page: 1,
      from: items.length ? 1 : null,
      to: items.length || null,
      ...meta,
    },
  }
}

export function opcionDeCompetencia(cambios: Partial<OpcionDeCompetencia> = {}): OpcionDeCompetencia {
  return { id: 10, codigo: 'COMP-0010', nombre: 'Colaboración', tipo: 'Cardinal', estado: 'Activo', ...cambios }
}
