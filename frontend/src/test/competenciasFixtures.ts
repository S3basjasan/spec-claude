import type { Competencia, CompetenciaDetalle, PaginaCompetencias } from '../modules/estructura/competencias/api'

export function competencia(cambios: Partial<Competencia> = {}): Competencia {
  return {
    id: 1,
    codigo: 'COMP-0001',
    nombre: 'Liderazgo',
    descripcion: 'Guiar e inspirar a otras personas.',
    tipo: 'Cardinal',
    estado: 'Activo',
    ...cambios,
  }
}

export function detalle(cambios: Partial<CompetenciaDetalle> = {}): CompetenciaDetalle {
  return {
    ...competencia(),
    version: 1,
    grados: [
      { grado: 'A', descripcion: 'Descripción del grado A.' },
      { grado: 'B', descripcion: 'Descripción del grado B.' },
      { grado: 'C', descripcion: 'Descripción del grado C.' },
      { grado: 'D', descripcion: 'Descripción del grado D.' },
    ],
    ...cambios,
  }
}

export function pagina(items: Competencia[], meta: Partial<PaginaCompetencias['meta']> = {}): PaginaCompetencias {
  return {
    data: items,
    meta: {
      current_page: 1,
      per_page: 10,
      total: items.length,
      last_page: 1,
      from: items.length ? 1 : null,
      to: items.length || null,
      ...meta,
    },
  }
}
