import type {
  Dashboard,
  FilaDetalle,
  FilaImpacto,
  FilaSeguimiento,
  Indicador,
  Meta,
  MiProgreso,
  OpcionesReportes,
  Pagina,
} from '../modules/desarrollo/reportes/api'

export function meta(sobrescribir: Partial<Meta> = {}): Meta {
  return { total: 1, page: 1, per_page: 10, last_page: 1, ...sobrescribir }
}

export function pagina<T>(data: T[], sobrescribir: Partial<Meta> = {}): Pagina<T> {
  return { data, meta: meta({ total: data.length, ...sobrescribir }) }
}

export function indicador(sobrescribir: Partial<Indicador> = {}): Indicador {
  return { disponible: true, valor: 50, anterior: 40, diferencia: 10, variacion_pct: 25, ...sobrescribir }
}

export function dashboard(sobrescribir: Partial<Dashboard> = {}): Dashboard {
  return {
    periodo: { desde: '2026-01-01', hasta: '2026-12-31' },
    periodo_anterior: { desde: '2025-01-01', hasta: '2025-12-31' },
    fuentes_no_disponibles: [],
    indicadores: {
      plan_cumplido: { ...indicador({ valor: 62.5, anterior: 50, diferencia: 12.5, variacion_pct: 25 }), acciones_finalizadas: 5, acciones_validas: 8 },
      total_horas: { ...indicador({ valor: 842, anterior: null, diferencia: null, variacion_pct: null }), horas_actividades: 790, horas_microlearning: 52 },
      satisfaccion: { ...indicador({ valor: 4.3, anterior: 4.1, diferencia: 0.2, variacion_pct: 4.9 }), respuestas: 27 },
      brechas_cerradas: { ...indicador({ valor: 11, anterior: 6, diferencia: 5, variacion_pct: 83.3 }), detectadas: 8 },
      roi_estimado: { ...indicador({ valor: 142, anterior: null, diferencia: null, variacion_pct: null }), acciones_evaluadas: 3 },
    },
    gestion: {
      inversion_por_colaborador: indicador({ valor: 310.5, anterior: 280, diferencia: 30.5, variacion_pct: 10.9 }),
      horas_por_colaborador: indicador({ valor: 31.2, anterior: null, diferencia: null, variacion_pct: null }),
      costo_por_hora: indicador({ valor: 9.9, anterior: null, diferencia: null, variacion_pct: null }),
    },
    graficos: {
      horas_por_departamento: {
        disponible: true,
        datos: [
          { departamento_id: 4, departamento: 'Tecnología', horas_actividades: 300, horas_microlearning: 20 },
          { departamento_id: null, departamento: 'Sin departamento', horas_actividades: 40, horas_microlearning: 5 },
        ],
      },
      evolucion_brechas: {
        disponible: true,
        datos: [
          { mes: '2026-01', activas: 14, cerradas: 1 },
          { mes: '2026-02', activas: 12, cerradas: 3 },
        ],
      },
      distribucion_por_tipo: {
        disponible: true,
        datos: [
          { tipo: 'Presencial', horas: 240 },
          { tipo: 'Virtual', horas: 100 },
          { tipo: 'ELearning', horas: 0 },
          { tipo: 'OJT', horas: 0 },
          { tipo: 'Codesarrollo', horas: 0 },
          { tipo: 'Mentoring', horas: 0 },
          { tipo: 'Microlearning', horas: 52 },
        ],
      },
    },
    ...sobrescribir,
  }
}

export function filaDetalle(sobrescribir: Partial<FilaDetalle> = {}): FilaDetalle {
  return {
    actividad_id: 1,
    codigo: 'ACT-0001',
    nombre: 'Liderazgo situacional',
    tipo: 'Presencial',
    estado: 'Finalizada',
    fecha_inicio: '2026-03-02',
    fecha_fin: '2026-03-13',
    departamento_id: 2,
    departamento: 'Talento Humano',
    asistentes: 8,
    inscritos: 10,
    horas_persona: 64,
    costo: 800,
    calificacion_promedio: 4.2,
    satisfaccion_promedio: 4.5,
    respuestas_satisfaccion: 6,
    ...sobrescribir,
  }
}

export function filaSeguimiento(sobrescribir: Partial<FilaSeguimiento> = {}): FilaSeguimiento {
  return {
    actividad_id: 1,
    codigo: 'ACT-0001',
    nombre: 'Liderazgo situacional',
    fecha_fin: '2026-03-13',
    elegibles: 8,
    hitos: [
      { hito: 30, vencimiento: '2026-04-12', respuestas: 6, tasa_respuesta: 75, promedio_aplicacion: 4.5, promedio_apoyo: 3.8 },
      { hito: 60, vencimiento: '2026-05-12', respuestas: 4, tasa_respuesta: 50, promedio_aplicacion: 3.5, promedio_apoyo: 3.2 },
      { hito: 90, vencimiento: '2026-06-11', respuestas: 0, tasa_respuesta: 0, promedio_aplicacion: null, promedio_apoyo: null },
    ],
    alerta_reingreso: true,
    ...sobrescribir,
  }
}

export function filaImpacto(sobrescribir: Partial<FilaImpacto> = {}): FilaImpacto {
  return {
    plan_accion_id: 21,
    plan_id: 4,
    plan_codigo: 'PLAN-0004',
    plan_nombre: 'Plan de desarrollo 2026',
    accion_nombre: 'Análisis con SQL',
    tipo: 'Virtual',
    fecha_fin: '2026-03-13',
    costo: 800,
    evaluada: false,
    roi: null,
    impacto: null,
    ...sobrescribir,
  }
}

export function opcionesReportes(): OpcionesReportes {
  return {
    departamentos: [
      { id: 1, nombre: 'Tecnología' },
      { id: 2, nombre: 'Talento Humano' },
    ],
    planes: [{ id: 4, codigo: 'PLAN-0004', nombre: 'Plan de desarrollo 2026', anio: 2026, estado: 'Ejecutando' }],
  }
}

export function miProgreso(sobrescribir: Partial<MiProgreso> = {}): MiProgreso {
  return {
    resumen: { formaciones_completadas: 7, competencias_adquiridas: 3, horas_acumuladas: 96.5, certificados: 2 },
    evolucion_competencias: [
      { competencia_id: 3, competencia: 'Liderazgo', nivel_primera: 'D', nivel_actual: 'B', nivel_requerido: 'B', solo_actual: false },
      { competencia_id: 4, competencia: 'Comunicación', nivel_primera: null, nivel_actual: 'C', nivel_requerido: 'A', solo_actual: true },
    ],
    certificados: [{ microcontenido_id: 31, titulo: 'Excel básico', fecha: '2026-08-12', descarga: '/api/microcontenidos/31/certificado' }],
    proximas_acciones: [{ accion_id: 12, tipo: 'Presencial', nombre: 'Taller de comunicación', fecha_inicio: '2026-11-04', fecha_fin: '2026-11-05', competencias: ['Comunicación'] }],
    pendientes: {
      encuestas: [{ actividad_id: 5, codigo: 'ACT-0005', nombre: 'Gestión del tiempo', fecha_fin: '2026-10-10', vence: '2026-11-09' }],
      seguimientos: [{ actividad_id: 4, codigo: 'ACT-0004', nombre: 'Liderazgo situacional', fecha_fin: '2026-09-24', hito: 30, vence: '2026-10-24' }],
    },
    fuentes_no_disponibles: [],
    ...sobrescribir,
  }
}
