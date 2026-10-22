import type {
  CompetenciaMicro,
  FilaMicro,
  Intento,
  Meta,
  Micro,
  MiAprendizaje,
  OpcionesMicro,
  PreguntaQuiz,
  ProgresoMicro,
  ResultadoRespuesta,
} from '../modules/desarrollo/micro-learning/api'

export function meta(sobrescribir: Partial<Meta> = {}): Meta {
  return { current_page: 1, per_page: 12, total: 1, last_page: 1, from: 1, to: 1, ...sobrescribir }
}

export function competencia(sobrescribir: Partial<CompetenciaMicro> = {}): CompetenciaMicro {
  return { id: 1, codigo: 'COMP-0001', nombre: 'Comunicación efectiva', estado: 'Activo', ...sobrescribir }
}

export function progreso(sobrescribir: Partial<ProgresoMicro> = {}): ProgresoMicro {
  return {
    estado: 'EnProgreso',
    posicion_segundos: null,
    fecha_inicio: '2026-10-15T10:00:00Z',
    fecha_completado: null,
    ultimo_puntaje: null,
    ...sobrescribir,
  }
}

export function fila(sobrescribir: Partial<FilaMicro> = {}): FilaMicro {
  return {
    id: 1,
    codigo: 'MICRO-0001',
    titulo: 'Cómo dar retroalimentación',
    tipo: 'Video',
    estado: 'Publicado',
    miniatura_url: null,
    duracion_minutos: 8,
    competencias: [competencia()],
    apto_conocimientos: true,
    completaciones: 0,
    puede_eliminarse: true,
    ...sobrescribir,
  }
}

export function pregunta(sobrescribir: Partial<PreguntaQuiz> = {}): PreguntaQuiz {
  return {
    id: 10,
    enunciado: '¿Cuándo dar retroalimentación?',
    orden: 1,
    opciones: [
      { id: 101, texto: 'Lo antes posible', orden: 1, es_correcta: true },
      { id: 102, texto: 'Nunca', orden: 2, es_correcta: false },
    ],
    ...sobrescribir,
  }
}

export function micro(sobrescribir: Partial<Micro> = {}): Micro {
  return {
    id: 1,
    codigo: 'MICRO-0001',
    titulo: 'Cómo dar retroalimentación',
    tipo: 'Video',
    estado: 'Publicado',
    miniatura_url: null,
    duracion_minutos: 8,
    competencias: [competencia()],
    apto_conocimientos: true,
    url: 'https://contenido.test/video',
    completaciones: 0,
    puede_eliminarse: true,
    tiene_intentos: false,
    ...sobrescribir,
  }
}

export function quiz(sobrescribir: Partial<Micro> = {}): Micro {
  return micro({
    id: 5,
    codigo: 'MICRO-0005',
    titulo: 'Quiz de retroalimentación',
    tipo: 'Quiz',
    url: null,
    preguntas: [
      pregunta({ id: 10, enunciado: 'Pregunta uno', orden: 1 }),
      pregunta({
        id: 11,
        enunciado: 'Pregunta dos',
        orden: 2,
        opciones: [
          { id: 111, texto: 'Opción A2', orden: 1, es_correcta: false },
          { id: 112, texto: 'Opción B2', orden: 2, es_correcta: true },
        ],
      }),
    ],
    ...sobrescribir,
  })
}

/** Quiz como lo recibe el colaborador: sin la opción correcta. */
export function sinCorrectas(m: Micro): Micro {
  return {
    ...m,
    preguntas: m.preguntas?.map((p) => ({ ...p, opciones: p.opciones.map((o) => ({ id: o.id, texto: o.texto, orden: o.orden })) })),
  }
}

export function intento(sobrescribir: Partial<Intento> = {}): Intento {
  const preguntas = sinCorrectas(quiz()).preguntas ?? []
  return { id: 77, estado: 'EnCurso', total_preguntas: preguntas.length, respondidas: [], preguntas, ...sobrescribir }
}

export function resultado(sobrescribir: Partial<ResultadoRespuesta> = {}): ResultadoRespuesta {
  return {
    es_correcta: true,
    opcion_correcta_id: 101,
    completado_ahora: false,
    intento: { estado: 'EnCurso', respondidas: [], puntaje: null, total_preguntas: 2 },
    ...sobrescribir,
  }
}

export function opciones(sobrescribir: Partial<OpcionesMicro> = {}): OpcionesMicro {
  return {
    competencias_activas: [
      { id: 1, codigo: 'COMP-0001', nombre: 'Comunicación efectiva' },
      { id: 2, codigo: 'COMP-0002', nombre: 'Liderazgo' },
    ],
    competencias: [competencia(), competencia({ id: 2, codigo: 'COMP-0002', nombre: 'Liderazgo' }), competencia({ id: 3, codigo: 'COMP-0003', nombre: 'Vieja', estado: 'Inactivo' })],
    ...sobrescribir,
  }
}

export function miAprendizaje(sobrescribir: Partial<MiAprendizaje> = {}): MiAprendizaje {
  return {
    resumen: { completados: 1, en_progreso: 1, horas_acumuladas: 0.5, certificados: 1 },
    asignados: [fila({ id: 1, titulo: 'Asignado uno', motivo: 'brecha', progreso: null })],
    completados: [fila({ id: 2, titulo: 'Completado uno', tipo: 'Quiz', progreso: progreso({ estado: 'Completado', fecha_completado: '2026-10-16T10:00:00Z' }), ultimo_puntaje: { correctas: 4, total: 5 }, completado_en: '2026-10-16T10:00:00Z' })],
    ...sobrescribir,
  }
}
