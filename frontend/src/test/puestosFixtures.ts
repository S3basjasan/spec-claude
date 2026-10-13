import type {
  CompetenciaAsignada,
  CompetenciaDisponible,
  FuncionDetalle,
  PaginaPuestos,
  PuestoDetalle,
  PuestoResumen,
  RequisitoDetalle,
} from '../modules/estructura/puestos/api'

export function puesto(cambios: Partial<PuestoResumen> = {}): PuestoResumen {
  return {
    id: 1,
    codigo: 'PU-001',
    nombre: 'Analista de datos',
    descripcion: 'Convierte datos en reportes para decidir mejor.',
    estado: 'Activo',
    ...cambios,
  }
}

export function funcion(cambios: Partial<FuncionDetalle> = {}): FuncionDetalle {
  return { orden: 1, descripcion: 'Recopilar y consolidar datos.', tipo: 'Esencial', ...cambios }
}

export function requisito(cambios: Partial<RequisitoDetalle> = {}): RequisitoDetalle {
  return { tipo: 'Formacion', descripcion: 'Título de tercer nivel afín.', ...cambios }
}

export function competenciaAsignada(cambios: Partial<CompetenciaAsignada> = {}): CompetenciaAsignada {
  return {
    competencia_id: 2,
    codigo: 'COMP-0002',
    nombre: 'Trabajo en equipo',
    tipo: 'Cardinal',
    estado: 'Activo',
    grado_requerido: 'B',
    ...cambios,
  }
}

export function detalle(cambios: Partial<PuestoDetalle> = {}): PuestoDetalle {
  return {
    ...puesto(),
    mision: 'Transformar los datos de la organización en información confiable.',
    version: 1,
    funciones: [
      funcion(),
      funcion({ orden: 2, descripcion: 'Documentar indicadores.', tipo: 'Complementaria' }),
    ],
    requisitos: [
      requisito(),
      requisito({ tipo: 'Experiencia', descripcion: '2 años en análisis de datos.' }),
      requisito({ tipo: 'Capacitacion', descripcion: 'Curso de SQL.' }),
    ],
    competencias: [competenciaAsignada()],
    ...cambios,
  }
}

export function pagina(items: PuestoResumen[], meta: Partial<PaginaPuestos['meta']> = {}): PaginaPuestos {
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
    tipo: 'Cardinal',
    grados: [
      { grado: 'A', descripcion: 'Adapta su mensaje a cualquier audiencia.' },
      { grado: 'B', descripcion: 'Expresa ideas con claridad.' },
      { grado: 'C', descripcion: 'Comunica lo necesario.' },
      { grado: 'D', descripcion: 'Transmite información básica.' },
    ],
    ...cambios,
  }
}
