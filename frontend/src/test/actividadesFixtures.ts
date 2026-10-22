import type {
  AccionDisponible,
  ActividadColaborador,
  ActividadDetalle,
  ActividadResumen,
  MisActividades,
  OpcionesActividades,
  Participante,
} from '../modules/desarrollo/actividades/api'

export function resumen(sobrescribir: Partial<ActividadResumen> = {}): ActividadResumen {
  return {
    id: 1,
    codigo: 'ACT-0001',
    accion_id: 11,
    nombre: 'Liderazgo situacional',
    tipo: 'Presencial',
    plan: { id: 4, codigo: 'PLAN-0004', nombre: 'Plan de desarrollo 2026', estado: 'Ejecutando' },
    fecha_inicio: '2026-03-02',
    fecha_fin: '2026-03-13',
    lugar_enlace: 'Sala de capacitación, piso 3',
    formador: 'Ps. Marco Salazar',
    departamento: { id: 2, nombre: 'Talento Humano' },
    capacidad: 20,
    inscritos: 3,
    estado: 'EnCurso',
    asistencia_pendiente: false,
    ...sobrescribir,
  }
}

export function participante(sobrescribir: Partial<Participante> = {}): Participante {
  return { colaborador_id: 10, nombre: 'Ana Pérez', asistencia: 'Pendiente', calificacion: null, observaciones: null, ...sobrescribir }
}

export function detalle(sobrescribir: Partial<ActividadDetalle> = {}): ActividadDetalle {
  return {
    ...resumen(),
    competencias: [{ id: 1, nombre: 'Liderazgo' }],
    horas: 16,
    version: 1,
    puede_editar: true,
    puede_registrar_asistencia: true,
    motivo_bloqueo: null,
    resumen_asistencia: { presentes: 0, ausentes: 0, justificados: 0, pendientes: 3, calificados: 0, promedio: null },
    participantes: [
      participante({ colaborador_id: 10, nombre: 'Ana Pérez' }),
      participante({ colaborador_id: 11, nombre: 'Luis Mora' }),
      participante({ colaborador_id: 12, nombre: 'Eva Ríos' }),
    ],
    auditoria: { creado_por: 'Marta Ríos', creado_en: '2026-03-01T10:00:00Z', modificado_por: 'Marta Ríos', modificado_en: '2026-03-01T10:00:00Z' },
    ...sobrescribir,
  }
}

export function accionDisponible(sobrescribir: Partial<AccionDisponible> = {}): AccionDisponible {
  return {
    accion_id: 31,
    nombre: 'Excel avanzado',
    tipo: 'Presencial',
    plan: { id: 4, codigo: 'PLAN-0004', nombre: 'Plan de desarrollo 2026' },
    competencias: [{ id: 2, nombre: 'Análisis de datos' }],
    fecha_inicio: '2026-04-06',
    fecha_fin: '2026-04-10',
    formador: 'Mg. Andrés Molina',
    capacidad: 15,
    inscritos: 8,
    ...sobrescribir,
  }
}

export function opciones(sobrescribir: Partial<OpcionesActividades> = {}): OpcionesActividades {
  return {
    departamentos: [
      { id: 1, nombre: 'Tecnología' },
      { id: 2, nombre: 'Talento Humano' },
    ],
    competencias: [
      { id: 1, nombre: 'Liderazgo' },
      { id: 2, nombre: 'Análisis de datos' },
    ],
    planes: [{ id: 4, codigo: 'PLAN-0004', nombre: 'Plan de desarrollo 2026', anio: 2026, estado: 'Ejecutando' }],
    ...sobrescribir,
  }
}

export function actividadColaborador(sobrescribir: Partial<ActividadColaborador> = {}): ActividadColaborador {
  return {
    id: 1,
    codigo: 'ACT-0001',
    nombre: 'Comunicación asertiva',
    tipo: 'Presencial',
    fecha_inicio: '2026-03-02',
    fecha_fin: '2026-03-13',
    lugar_enlace: 'Aula 2, edificio B',
    formador: 'Dra. Paula Ortiz',
    estado: 'EnCurso',
    asistencia: 'Pendiente',
    calificacion: null,
    ...sobrescribir,
  }
}

export function misActividades(sobrescribir: Partial<MisActividades> = {}): MisActividades {
  return {
    resumen: { inscritas: 2, finalizadas: 1, asistencias_registradas: 1, presentes: 1, asistencia_porcentaje: 100, calificadas: 1, calificacion_promedio: 4 },
    proximas: [actividadColaborador({ id: 2, nombre: 'Próxima actividad', estado: 'Programada', fecha_inicio: '2026-10-20', fecha_fin: '2026-10-21' })],
    historial: [actividadColaborador({ id: 3, nombre: 'Actividad pasada', estado: 'Finalizada', fecha_inicio: '2026-02-10', fecha_fin: '2026-02-11', asistencia: 'Presente', calificacion: 4 })],
    ...sobrescribir,
  }
}
