import type {
  CompetenciaDeNivel,
  EventoDeHistorial,
  Inscripcion,
  InscripcionPropia,
  MiRuta,
  Nivel,
  OpcionesRutas,
  RutaDetalle,
  RutaResumen,
} from '../modules/desarrollo/rutas/api'

const competencia = (id: number, nombre: string, grado: CompetenciaDeNivel['grado'], diferencial: CompetenciaDeNivel['diferencial']): CompetenciaDeNivel => ({
  competencia_id: id,
  codigo: `COM-00${id}`,
  nombre,
  tipo: 'Cardinal',
  grado,
  diferencial,
})

export function nivel(sobrescribir: Partial<Nivel> = {}): Nivel {
  return {
    id: 101,
    orden: 1,
    puesto_id: 1,
    puesto: { codigo: 'PU-001', nombre: 'Asistente de datos', activo: true },
    nombre: 'Asistente de datos',
    experiencia_minima_anios: null,
    competencias: [competencia(1, 'Comunicación', 'C', null), competencia(2, 'Análisis', 'D', null)],
    competencias_quitadas: [],
    conocimientos: { informativo: true, formacion: ['Bachiller'], capacitacion: [] },
    experiencia: { anios_minimos: null, diferencial_anios: null, requisitos: ['Sin experiencia'] },
    en_uso: false,
    inscritos_activos: 0,
    ...sobrescribir,
  }
}

/** Tres niveles con competencias que suben de grado, una nueva y un diferencial de experiencia. */
export function nivelesDeEjemplo(): Nivel[] {
  return [
    nivel(),
    nivel({
      id: 102,
      orden: 2,
      puesto_id: 2,
      puesto: { codigo: 'PU-002', nombre: 'Analista de datos', activo: true },
      nombre: 'Analista de datos',
      experiencia_minima_anios: 1,
      competencias: [
        competencia(1, 'Comunicación', 'B', { tipo: 'sube', antes: 'C', delta: 1 }),
        competencia(2, 'Análisis', 'C', { tipo: 'sube', antes: 'D', delta: 1 }),
        competencia(3, 'Liderazgo', 'C', { tipo: 'nuevo' }),
      ],
      conocimientos: { informativo: true, formacion: ['Tecnólogo'], capacitacion: ['Excel avanzado'] },
      experiencia: { anios_minimos: 1, diferencial_anios: null, requisitos: ['1 año'] },
      inscritos_activos: 1,
    }),
    nivel({
      id: 103,
      orden: 3,
      puesto_id: 3,
      puesto: { codigo: 'PU-003', nombre: 'Analista senior', activo: true },
      nombre: 'Analista senior',
      experiencia_minima_anios: 3,
      competencias: [
        competencia(1, 'Comunicación', 'A', { tipo: 'sube', antes: 'B', delta: 1 }),
        competencia(2, 'Análisis', 'C', { tipo: 'igual' }),
        competencia(3, 'Liderazgo', 'B', { tipo: 'sube', antes: 'C', delta: 1 }),
      ],
      conocimientos: { informativo: true, formacion: ['Ingeniería'], capacitacion: ['Excel avanzado', 'SQL'] },
      experiencia: { anios_minimos: 3, diferencial_anios: 2, requisitos: ['3 años'] },
    }),
  ]
}

export function rutaResumen(sobrescribir: Partial<RutaResumen> = {}): RutaResumen {
  return {
    id: 1,
    codigo: 'CAR-0001',
    nombre: 'Carrera de especialista en datos',
    descripcion: 'Del análisis de datos al liderazgo técnico.',
    tipo: 'Especialista',
    estado: 'Vigente',
    departamento_id: 1,
    departamento: 'Tecnología',
    niveles_count: 3,
    inscritos_count: 2,
    ...sobrescribir,
  }
}

export function rutaDetalle(sobrescribir: Partial<RutaDetalle> = {}): RutaDetalle {
  return {
    id: 1,
    codigo: 'CAR-0001',
    nombre: 'Carrera de especialista en datos',
    tipo: 'Especialista',
    descripcion: 'Del análisis de datos al liderazgo técnico.',
    estado: 'Vigente',
    departamento_id: 1,
    departamento: 'Tecnología',
    version: 2,
    creada_por: 'Administrador de prueba',
    creada_at: '2026-10-20T10:00:00-05:00',
    modificada_por: 'Administrador de prueba',
    modificada_at: '2026-10-21T09:00:00-05:00',
    niveles: nivelesDeEjemplo(),
    inscritos_count: 2,
    sin_nivel: 1,
    fuentes_no_disponibles: [],
    ...sobrescribir,
  }
}

export function opcionesRutas(sobrescribir: Partial<OpcionesRutas> = {}): OpcionesRutas {
  return {
    tipos: [
      { valor: 'Especialista', nombre: 'Especialista', admite_departamento: true },
      { valor: 'Gerencial', nombre: 'Gerencial', admite_departamento: true },
      { valor: 'Interareas', nombre: 'Interáreas', admite_departamento: false },
      { valor: 'JovenesProfesionales', nombre: 'Jóvenes profesionales', admite_departamento: true },
    ],
    departamentos: [
      { id: 1, nombre: 'Tecnología' },
      { id: 2, nombre: 'Talento Humano' },
    ],
    puestos: [
      { id: 1, codigo: 'PU-001', nombre: 'Asistente de datos' },
      { id: 2, codigo: 'PU-002', nombre: 'Analista de datos' },
      { id: 3, codigo: 'PU-003', nombre: 'Analista senior' },
      { id: 4, codigo: 'PU-004', nombre: 'Jefe de datos' },
    ],
    fuentes_no_disponibles: [],
    ...sobrescribir,
  }
}

export function inscripcion(sobrescribir: Partial<Inscripcion> = {}): Inscripcion {
  return {
    id: 1,
    ruta_id: 1,
    ruta: { id: 1, codigo: 'CAR-0001', nombre: 'Carrera de especialista en datos', estado: 'Vigente' },
    colaborador: { id: 10, nombre: 'Ana Núñez', departamento: 'Tecnología' },
    nivel_actual: { id: 101, orden: 1, nombre: 'Asistente de datos' },
    nivel_objetivo: { id: 103, orden: 3, nombre: 'Analista senior' },
    estado: 'Activa',
    motivo: null,
    created_at: '2026-10-20T10:00:00-05:00',
    resuelta_at: null,
    ...sobrescribir,
  }
}

export function evento(sobrescribir: Partial<EventoDeHistorial> = {}): EventoDeHistorial {
  return {
    id: 1,
    tipo: 'Ruta',
    accion: 'estado_cambiado',
    inscripcion_id: null,
    colaborador: null,
    valor_anterior: { estado: 'Borrador' },
    valor_nuevo: { estado: 'Vigente' },
    motivo: null,
    usuario: 'Administrador de prueba',
    created_at: '2026-10-21T09:00:00-05:00',
    ...sobrescribir,
  }
}

export function inscripcionPropia(sobrescribir: Partial<InscripcionPropia> = {}): InscripcionPropia {
  return {
    inscripcion_id: 1,
    ruta: { id: 1, codigo: 'CAR-0001', nombre: 'Carrera de especialista en datos', tipo: 'Especialista' },
    niveles: nivelesDeEjemplo().map((n) => {
      const copia = { ...n }
      delete copia.en_uso
      delete copia.inscritos_activos
      return copia
    }),
    nivel_actual_id: 101,
    nivel_objetivo_id: 103,
    siguiente_nivel_id: 102,
    puede_ver_adecuacion: false,
    ...sobrescribir,
  }
}

export function miRuta(sobrescribir: Partial<MiRuta> = {}): MiRuta {
  return {
    inscripciones: [inscripcionPropia()],
    rutas_disponibles: [{ id: 2, codigo: 'CAR-0002', nombre: 'Carrera gerencial', tipo: 'Gerencial', niveles_count: 4 }],
    aviso: 'Esta ruta es una guía de desarrollo; no implica la promesa de un puesto.',
    fuentes_no_disponibles: [],
    ...sobrescribir,
  }
}
