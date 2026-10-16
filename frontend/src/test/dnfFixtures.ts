import type {
  AvisosDnf,
  Brecha,
  Dnf,
  DnfGenerada,
  FilaHistorial,
  MiDiagnostico,
  OpcionesDnf,
  PaginaHistorial,
  ResultadoColaborador,
} from '../modules/desarrollo/dnf/api'

export function brecha(sobrescribir: Partial<Brecha> = {}): Brecha {
  return {
    competencia_id: 1,
    codigo: 'COM-001',
    nombre: 'Análisis de datos',
    tipo: 'Cardinal',
    nivel_requerido: 'A',
    nivel_actual: 'C',
    diferencia: -2,
    brecha_porcentaje: 50,
    prioridad: 'Alta',
    estado: 'ConBrecha',
    competencia_activa: true,
    ...sobrescribir,
  }
}

export function resultado(sobrescribir: Partial<ResultadoColaborador> = {}): ResultadoColaborador {
  const brechas = sobrescribir.brechas ?? [
    brecha(),
    brecha({ competencia_id: 2, codigo: 'COM-002', nombre: 'Trabajo en equipo', nivel_requerido: 'B', nivel_actual: 'B', diferencia: 0, brecha_porcentaje: 0, prioridad: null, estado: 'Cumple' }),
    brecha({ competencia_id: 3, codigo: 'COM-003', nombre: 'Liderazgo', nivel_actual: null, diferencia: null, brecha_porcentaje: null, prioridad: null, estado: 'SinEvaluacion' }),
  ]
  return {
    colaborador_id: 10,
    nombre: 'Diego Paredes',
    puesto: { id: 1, codigo: 'PU-014', nombre: 'Analista de datos' },
    cantidad_brechas: brechas.filter((b) => b.estado === 'ConBrecha').length,
    prioridad_mas_alta: 'Alta',
    sin_evaluacion: brechas.filter((b) => b.estado === 'SinEvaluacion').length,
    brechas,
    ...sobrescribir,
  }
}

export function dnf(sobrescribir: Partial<Dnf> = {}): Dnf {
  const colaboradores = sobrescribir.colaboradores ?? [resultado()]
  return {
    id: 7,
    codigo: 'DNF-0007',
    alcance: 'Individual',
    fecha: '2026-10-15T14:03:00+00:00',
    departamento: null,
    generado_por: { id: 1, nombre: 'Marta Ríos' },
    competencias_omitidas: 0,
    plan_formacion: null,
    resumen: {
      colaboradores: colaboradores.length,
      cantidad_brechas: colaboradores.reduce((n, c) => n + c.cantidad_brechas, 0),
      prioridad_mas_alta: 'Alta',
      sin_evaluacion: colaboradores.reduce((n, c) => n + c.sin_evaluacion, 0),
    },
    colaboradores,
    ...sobrescribir,
  }
}

export function avisos(sobrescribir: Partial<AvisosDnf> = {}): AvisosDnf {
  return { competencias_omitidas: 0, sin_evaluacion: 0, excluidos: [], ...sobrescribir }
}

export function generada(sobrescribirDnf: Partial<Dnf> = {}, sobrescribirAvisos: Partial<AvisosDnf> = {}): DnfGenerada {
  return { data: dnf(sobrescribirDnf), avisos: avisos(sobrescribirAvisos) }
}

export function opciones(sobrescribir: Partial<OpcionesDnf> = {}): OpcionesDnf {
  return {
    colaboradores: [
      { id: 10, nombre: 'Diego Paredes', puesto: 'Analista de datos', departamento_id: 5 },
      { id: 11, nombre: 'Camila Andrade', puesto: 'Desarrolladora de software', departamento_id: 5 },
    ],
    departamentos: [{ id: 5, nombre: 'Tecnología', colaboradores_evaluables: 2 }],
    ...sobrescribir,
  }
}

export function filaHistorial(sobrescribir: Partial<FilaHistorial> = {}): FilaHistorial {
  return {
    id: 7,
    codigo: 'DNF-0007',
    fecha: '2026-10-15T14:03:00+00:00',
    alcance: 'Individual',
    colaborador: { id: 10, nombre: 'Diego Paredes' },
    departamento: null,
    cantidad_brechas: 3,
    prioridad_mas_alta: 'Alta',
    tiene_plan: false,
    ...sobrescribir,
  }
}

export function paginaHistorial(filas: FilaHistorial[], sobrescribirMeta: Partial<PaginaHistorial['meta']> = {}): PaginaHistorial {
  return { data: filas, meta: { page: 1, per_page: 15, total: filas.length, last_page: 1, ...sobrescribirMeta } }
}

export function miDiagnostico(sobrescribir: Partial<MiDiagnostico> = {}): MiDiagnostico {
  const alta = brecha()
  const media = brecha({ competencia_id: 4, codigo: 'COM-004', nombre: 'Comunicación efectiva', nivel_requerido: 'B', nivel_actual: 'C', diferencia: -1, brecha_porcentaje: 33.33, prioridad: 'Media' })
  return {
    dnf: { id: 7, codigo: 'DNF-0007', fecha: '2026-10-15T14:03:00+00:00' },
    puesto: { codigo: 'PU-014', nombre: 'Analista de datos' },
    resumen: { con_brecha: 2, cumplen: 1, sin_evaluacion: 0 },
    prioritarias: [alta, media],
    brechas: [alta, media],
    cumplen: [{ competencia_id: 2, nombre: 'Trabajo en equipo', nivel_actual: 'B', estado: 'Cumple' }],
    tiene_oferta: { '1': true, '4': false },
    ...sobrescribir,
  }
}
