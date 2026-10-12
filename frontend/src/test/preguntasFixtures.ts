import type { CompetenciaDisponible, PaginaRegistros, RegistroDetalle, RegistroResumen } from '../modules/estructura/preguntas/api'

export function registro(cambios: Partial<RegistroResumen> = {}): RegistroResumen {
  return {
    competencia_id: 1,
    codigo: 'COMP-0001',
    nombre: 'Liderazgo',
    descripcion: 'Guiar e inspirar a otras personas.',
    tipo: 'Cardinal',
    estado: 'Activo',
    ...cambios,
  }
}

export function detalle(cambios: Partial<RegistroDetalle> = {}): RegistroDetalle {
  return {
    ...registro(),
    version: 1,
    preguntas: [
      'Cuéntame de una meta que lograste.',
      '¿Cómo organizas tu trabajo?',
      'Describe un error y qué aprendiste.',
      '¿Cómo trabajas bajo presión?',
    ],
    ...cambios,
  }
}

export function pagina(items: RegistroResumen[], meta: Partial<PaginaRegistros['meta']> = {}): PaginaRegistros {
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

export function competenciaDisponible(cambios: Partial<CompetenciaDisponible> = {}): CompetenciaDisponible {
  return {
    competencia_id: 3,
    codigo: 'COMP-0003',
    nombre: 'Comunicación efectiva',
    descripcion: 'Transmitir ideas con claridad.',
    tipo: 'Cardinal',
    ...cambios,
  }
}
