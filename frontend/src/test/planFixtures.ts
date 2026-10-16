import type {
  Accion,
  AccionMiPlan,
  FilaAccion,
  FilaPlan,
  MiPlan,
  OpcionesPlan,
  PaginaAcciones,
  PaginaColaboradores,
  PaginaHistorial,
  PaginaPlanes,
  Plan,
  Puede,
  RenglonHistorial,
} from '../modules/desarrollo/plan-formacion/api'

export function puede(cambios: Partial<Puede> = {}): Puede {
  return {
    editar: false,
    enviar_revision: false,
    aprobar: false,
    devolver: false,
    iniciar: false,
    cerrar: false,
    reasignar_aprobador: false,
    cambiar_participantes: false,
    cancelar_acciones: false,
    ...cambios,
  }
}

export function accion(cambios: Partial<Accion> = {}): Accion {
  return {
    id: 7,
    plan_id: 1,
    curso_id: null,
    nombre: 'Análisis con SQL',
    tipo: 'Virtual',
    fecha_inicio: '2026-03-02',
    fecha_fin: '2026-03-13',
    duracion_horas: 16,
    formador: 'Ing. Vera',
    capacidad_maxima: 20,
    costo: 800,
    cancelada: false,
    motivo_cancelacion: null,
    estado_accion: 'Programada',
    competencias: [{ id: 1, codigo: 'COM-001', nombre: 'Análisis de datos', activa: true }],
    participantes: [
      { colaborador_id: 10, nombre: 'Ana Pérez', vinculo_vigente: true },
      { colaborador_id: 11, nombre: 'Luis Mora', vinculo_vigente: true },
    ],
    completa: true,
    faltantes: [],
    ...cambios,
  }
}

export function plan(cambios: Partial<Plan> = {}): Plan {
  return {
    id: 1,
    codigo: 'PLAN-0001',
    nombre: 'Plan técnico 2026',
    anio: 2026,
    descripcion: null,
    estado: 'Borrador',
    aprobador: { id: 5, nombre: 'Marta Ríos' },
    origen_dnf: null,
    fecha_aprobacion: null,
    cantidad_acciones: 1,
    costo_total: 800,
    horas_totales: 16,
    creado_por: { id: 5, nombre: 'Marta Ríos' },
    creado_en: '2026-10-16T10:00:00-05:00',
    modificado_por: { id: 5, nombre: 'Marta Ríos' },
    modificado_en: '2026-10-16T10:30:00-05:00',
    acciones: [accion()],
    avisos_cruce: [],
    puede: puede({ editar: true, enviar_revision: true, cambiar_participantes: true }),
    ...cambios,
  }
}

export function filaPlan(cambios: Partial<FilaPlan> = {}): FilaPlan {
  return {
    id: 1,
    codigo: 'PLAN-0001',
    nombre: 'Plan técnico 2026',
    anio: 2026,
    estado: 'Borrador',
    cantidad_acciones: 3,
    costo_total: 1500.5,
    aprobador: { id: 5, nombre: 'Marta Ríos' },
    puede: puede({ editar: true, enviar_revision: true }),
    ...cambios,
  }
}

export function paginaPlanes(filas: FilaPlan[], meta: Partial<PaginaPlanes['meta']> = {}): PaginaPlanes {
  return { data: filas, meta: { page: 1, per_page: 10, total: filas.length, last_page: 1, ...meta } }
}

export function opcionesPlan(cambios: Partial<OpcionesPlan> = {}): OpcionesPlan {
  return {
    aprobadores: [
      { id: 5, nombre: 'Marta Ríos' },
      { id: 6, nombre: 'Esteban Rivas' },
    ],
    tipos: ['Presencial', 'Virtual', 'ELearning', 'OJT', 'Codesarrollo', 'Mentoring'],
    competencias: [
      { id: 1, codigo: 'COM-001', nombre: 'Análisis de datos' },
      { id: 2, codigo: 'COM-002', nombre: 'Liderazgo' },
      { id: 3, codigo: 'COM-003', nombre: 'Comunicación efectiva' },
    ],
    ...cambios,
  }
}

export function paginaColaboradores(): PaginaColaboradores {
  return {
    data: [
      { id: 10, codigo: 'COL-0010', nombre: 'Ana Pérez' },
      { id: 11, codigo: 'COL-0011', nombre: 'Luis Mora' },
      { id: 12, codigo: 'COL-0012', nombre: 'Eva Ríos' },
    ],
    meta: { page: 1, per_page: 20, total: 3, last_page: 1 },
  }
}

export function filaAccion(cambios: Partial<FilaAccion> = {}): FilaAccion {
  return {
    id: 7,
    plan: { id: 1, codigo: 'PLAN-0001', nombre: 'Plan técnico 2026', estado: 'Aprobado' },
    nombre: 'Análisis con SQL',
    competencias: [{ id: 1, nombre: 'Análisis de datos' }],
    tipo: 'Virtual',
    fecha_inicio: '2026-10-05',
    fecha_fin: '2026-10-23',
    formador: 'Ing. Vera',
    inscritos: 18,
    capacidad_maxima: 20,
    costo: 1200,
    cancelada: false,
    motivo_cancelacion: null,
    estado_accion: 'EnCurso',
    ...cambios,
  }
}

export function paginaAcciones(filas: FilaAccion[], meta: Partial<PaginaAcciones['meta']> = {}): PaginaAcciones {
  return { data: filas, meta: { page: 1, per_page: 10, total: filas.length, last_page: 1, ...meta } }
}

export function renglonHistorial(cambios: Partial<RenglonHistorial> = {}): RenglonHistorial {
  return {
    id: 1,
    tipo: 'Estado',
    accion_id: null,
    accion_nombre: null,
    valor_anterior: 'Borrador',
    valor_nuevo: 'Revision',
    motivo: null,
    usuario: { id: 5, nombre: 'Marta Ríos' },
    fecha: '2026-10-16T10:40:00-05:00',
    ...cambios,
  }
}

export function paginaHistorial(renglones: RenglonHistorial[]): PaginaHistorial {
  return { data: renglones, meta: { page: 1, per_page: 10, total: renglones.length, last_page: 1 } }
}

export function accionMiPlan(cambios: Partial<AccionMiPlan> = {}): AccionMiPlan {
  return {
    id: 7,
    plan: { id: 1, nombre: 'Plan técnico 2026', anio: 2026 },
    nombre: 'Visualización de datos con tableros',
    competencias: [{ id: 1, nombre: 'Análisis de datos' }],
    tipo: 'Virtual',
    fecha_inicio: '2026-10-05',
    fecha_fin: '2026-10-23',
    formador: 'Mg. Andrés Molina',
    duracion_horas: 20,
    estado_accion: 'EnCurso',
    motivo_cancelacion: null,
    completada: false,
    ...cambios,
  }
}

export function miPlan(cambios: Partial<MiPlan> = {}): MiPlan {
  const acciones = [accionMiPlan()]
  return { porcentaje_completado: 0, acciones_asignadas: 1, acciones_completadas: 0, acciones, proximas: acciones, ...cambios }
}
