import type {
  Adecuacion,
  CumplimientoPorDimension,
  Diagnostico,
  OpcionesDeColaborador,
  OpcionesDeColaboradores,
  ResumenAdecuacion,
  Resultado,
} from '../modules/desarrollo/adecuacion/api'

/** Cumplimiento de ejemplo por dimensión (0..1); `null` = dimensión no evaluada. */
export function cumplimiento(sobrescribir: Partial<CumplimientoPorDimension> = {}): CumplimientoPorDimension {
  return { Experiencia: 1, Conocimiento: null, Competencia: 0.75, ...sobrescribir }
}

export function resultado(sobrescribir: Partial<Resultado> = {}): Resultado {
  return {
    dimension: 'Competencia',
    tipo_elemento: 'Competencia',
    elemento_id: 1,
    nombre: 'Comunicación',
    requerido_actual: 'C',
    requerido_objetivo: 'B',
    valor: 'C',
    requerido_actual_anios: null,
    requerido_objetivo_anios: null,
    valor_anios: null,
    diferencia: -1,
    estado: 'ConBrecha',
    estado_etiqueta: 'Con brecha',
    tiempo_cierre_meses: null,
    cumplimiento: 0.67,
    ...sobrescribir,
  }
}

export const resultadoCumple = (): Resultado =>
  resultado({ elemento_id: 2, nombre: 'Análisis', requerido_objetivo: 'C', valor: 'C', diferencia: 0, estado: 'Cumple', estado_etiqueta: 'Cumple', cumplimiento: 1 })

export const resultadoSinEvaluar = (): Resultado =>
  resultado({ elemento_id: 3, nombre: 'Liderazgo', requerido_actual: null, requerido_objetivo: 'C', valor: null, diferencia: null, estado: 'SinEvaluacion', estado_etiqueta: 'Sin evaluación', cumplimiento: null })

export const resultadoExperiencia = (): Resultado =>
  resultado({
    dimension: 'Experiencia',
    tipo_elemento: null,
    elemento_id: null,
    nombre: null,
    requerido_actual: null,
    requerido_objetivo: null,
    valor: null,
    requerido_objetivo_anios: 5,
    valor_anios: 3,
    diferencia: -2,
    cumplimiento: 0.6,
  })

export function diagnostico(sobrescribir: Partial<Diagnostico> = {}): Diagnostico {
  return {
    colaborador: { id: 10, nombre: 'Diego Paredes', departamento: 'Tecnología' },
    puesto_actual: { id: 1, nombre: 'Asistente de datos' },
    puesto_objetivo: { id: 2, nombre: 'Analista de datos' },
    inscripcion_id: 9,
    tipo_cambio: 'Promocion',
    experiencia_requerida_anios: 5,
    experiencia_de_la_persona_anios: 3,
    resultados: [resultado(), resultadoCumple(), resultadoExperiencia()],
    cumplimiento_por_dimension: { Experiencia: 0.6, Conocimiento: null, Competencia: 0.83 },
    cumplimiento_por_dimension_actual: { Experiencia: null, Conocimiento: null, Competencia: 1 },
    cumplimiento_actual: 100,
    cumplimiento_objetivo: 71.5,
    preparacion_provisoria: 'EnDesarrollo',
    umbral_meses: 12,
    avisos: [{ tipo: 'conocimientos_desactivados', mensaje: 'Los conocimientos no se evalúan.', cantidad: 0 }],
    fuentes_no_disponibles: [],
    ...sobrescribir,
  }
}

export function adecuacion(sobrescribir: Partial<Adecuacion> = {}): Adecuacion {
  return {
    id: 1,
    codigo: 'ADE-0001',
    fecha: '2026-10-21T10:00:00-05:00',
    generada_por: 'Marta Ríos',
    colaborador: { id: 10, nombre: 'Diego Paredes', departamento: 'Tecnología' },
    puesto_actual: { id: 1, nombre: 'Asistente de datos' },
    puesto_objetivo: { id: 2, nombre: 'Analista de datos' },
    inscripcion: { id: 9, ruta_nombre: 'Carrera de especialista' },
    experiencia_requerida_anios: 5,
    preparacion: 'EnDesarrollo',
    preparacion_etiqueta: 'En desarrollo',
    umbral_meses: 12,
    observaciones: null,
    es_vigente: true,
    resultados: [resultado({ tiempo_cierre_meses: 6 }), resultadoCumple(), resultadoExperiencia()],
    cumplimiento_por_dimension: { Experiencia: 0.6, Conocimiento: null, Competencia: 0.83 },
    cumplimiento_por_dimension_actual: { Experiencia: null, Conocimiento: null, Competencia: 1 },
    brechas: 2,
    tiempo_maximo_cierre_meses: 6,
    acciones: {
      crear_plan: { habilitado: false, motivo: 'Disponible cuando exista el Plan de Desarrollo Individual.' },
      incluir_programa: { habilitado: false, motivo: 'Disponible cuando exista el Plan de Desarrollo Individual.' },
    },
    fuentes_no_disponibles: [],
    ...sobrescribir,
  }
}

export function opcionesDeColaboradores(): OpcionesDeColaboradores {
  return {
    colaboradores: [
      { id: 10, codigo: 'COL-0010', nombre: 'Diego Paredes', departamento: 'Tecnología' },
      { id: 11, codigo: 'COL-0011', nombre: 'Lucía Andrade', departamento: 'Talento Humano' },
    ],
    puestos: [
      { id: 2, codigo: 'PU-002', nombre: 'Analista de datos' },
      { id: 3, codigo: 'PU-003', nombre: 'Analista senior' },
    ],
    departamentos: [
      { id: 1, nombre: 'Tecnología' },
      { id: 2, nombre: 'Talento Humano' },
    ],
    rutas: [
      { id: 1, nombre: 'Carrera de especialista' },
      { id: 2, nombre: 'Carrera gerencial' },
    ],
    fuentes_no_disponibles: [],
  }
}

export function opcionesDeColaborador(sobrescribir: Partial<OpcionesDeColaborador> = {}): OpcionesDeColaborador {
  return {
    inscripciones: [
      {
        inscripcion_id: 9,
        ruta_id: 1,
        ruta_nombre: 'Carrera de especialista',
        nivel_actual: { id: 101, orden: 1, puesto_id: 1, nombre: 'Asistente' },
        nivel_objetivo: { id: 102, orden: 2, puesto_id: 2, nombre: 'Analista', experiencia_minima_anios: 5 },
      },
    ],
    puestos: [
      { id: 2, codigo: 'PU-002', nombre: 'Analista de datos' },
      { id: 3, codigo: 'PU-003', nombre: 'Analista senior' },
    ],
    historial: [],
    fuentes_no_disponibles: [],
    ...sobrescribir,
  }
}

export function resumenAdecuacion(sobrescribir: Partial<ResumenAdecuacion> = {}): ResumenAdecuacion {
  return {
    id: 1,
    codigo: 'ADE-0001',
    fecha: '2026-10-21 10:00:00',
    colaborador: { id: 10, nombre: 'Diego Paredes' },
    puesto_objetivo: { id: 2, nombre: 'Analista de datos' },
    preparacion: 'EnDesarrollo',
    preparacion_etiqueta: 'En desarrollo',
    brechas: 2,
    es_vigente: true,
    ...sobrescribir,
  }
}
