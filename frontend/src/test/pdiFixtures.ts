import type { Accion, Compromiso, Hito, MiPlan, OpcionesDeColaborador, OpcionesGenerales, Pdi, PdiResumen, Precarga } from '../modules/desarrollo/pdi/api'

const permitidas = {
  editar: false,
  acordar: false,
  devolver: false,
  avance: false,
  vincular: false,
  cerrar: false,
  cambiar_responsable: false,
  aceptar: false,
  devolver_colaborador: false,
}

export function accion(sobrescribir: Partial<Accion> = {}): Accion {
  return {
    id: 100,
    orden: 1,
    tipo: 'Curso',
    descripcion: 'Curso de comunicación efectiva',
    persona_cargo: null,
    fecha_inicio: '2026-07-15',
    fecha_fin: '2026-09-30',
    estado: 'Pendiente',
    estado_derivado: false,
    vinculo: null,
    ...sobrescribir,
  }
}

export function compromiso(sobrescribir: Partial<Compromiso> = {}): Compromiso {
  return {
    id: 10,
    orden: 1,
    dimension: 'Competencia',
    tipo_elemento: 'Competencia',
    elemento: { id: 1, nombre: 'Comunicación efectiva' },
    descripcion: null,
    nivel_actual: 'C',
    nivel_objetivo: 'B',
    indicador: 'Evaluación 360 ≥ B',
    fecha_inicio: '2026-07-01',
    fecha_fin: '2026-11-30',
    resultado: null,
    acciones: [accion()],
    ...sobrescribir,
  }
}

export function hito(sobrescribir: Partial<Hito> = {}): Hito {
  return { id: 50, fecha: '2026-09-01', descripcion: 'Primera revisión de avance', estado: 'Pendiente', observaciones: null, vencido: false, ...sobrescribir }
}

export function resumen(sobrescribir: Partial<PdiResumen> = {}): PdiResumen {
  return {
    id: 1,
    codigo: 'PDI-0001',
    colaborador: { id: 10, nombre: 'Diego Paredes', departamento: 'Tecnología' },
    tipo: 'Mejora',
    estado: 'Borrador',
    objetivo: 'Cerrar las brechas de comunicación.',
    puesto_objetivo: null,
    responsable: { id: 1, nombre: 'Marta Ríos', sin_permiso: false },
    fecha_inicio: '2026-07-01',
    fecha_fin: '2026-12-31',
    avance: null,
    hitos_vencidos: 0,
    resultado: null,
    version: 1,
    ...sobrescribir,
  }
}

export function pdi(sobrescribir: Partial<Pdi> = {}): Pdi {
  const { resultado, ...base } = resumen()
  void resultado

  return {
    ...base,
    origen: null,
    acuerdo_responsable: null,
    aceptacion_colaborador: null,
    comentario_colaborador: null,
    resultado: null,
    resultado_etiqueta: null,
    conclusion: null,
    creado_por: 'Marta Ríos',
    creado_en: '2026-06-10T09:00:00+00:00',
    cerrado_en: null,
    compromisos: [compromiso()],
    hitos: [hito()],
    completitud: [],
    acciones_permitidas: { ...permitidas, editar: true, acordar: true },
    ...sobrescribir,
  }
}

export function pdiAcordado(sobrescribir: Partial<Pdi> = {}): Pdi {
  return pdi({
    estado: 'Acordado',
    version: 3,
    acuerdo_responsable: { fecha: '2026-06-10T09:00:00+00:00', usuario: 'Marta Ríos' },
    aceptacion_colaborador: '2026-06-11T09:00:00+00:00',
    acciones_permitidas: { ...permitidas, devolver: true, avance: true, vincular: true, cerrar: true, cambiar_responsable: true },
    ...sobrescribir,
  })
}

export function pdiParaElColaborador(sobrescribir: Partial<Pdi> = {}): Pdi {
  return pdi({
    version: 2,
    acuerdo_responsable: { fecha: '2026-06-10T09:00:00+00:00', usuario: 'Marta Ríos' },
    acciones_permitidas: { ...permitidas, aceptar: true, devolver_colaborador: true },
    ...sobrescribir,
  })
}

export function opcionesGenerales(): OpcionesGenerales {
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
    responsables: [
      { id: 1, nombre: 'Marta Ríos' },
      { id: 7, nombre: 'Roberto Salgado' },
    ],
    competencias: [
      { id: 1, codigo: 'COM-001', nombre: 'Comunicación efectiva' },
      { id: 2, codigo: 'COM-002', nombre: 'Trabajo en equipo' },
    ],
    dimensiones_disponibles: ['Competencia', 'Experiencia', 'Meta'],
  }
}

export function opcionesDeColaborador(sobrescribir: Partial<OpcionesDeColaborador> = {}): OpcionesDeColaborador {
  return {
    puesto_objetivo_sugerido: null,
    plan_abierto: { Mejora: null, Carrera: null, Talento: null },
    dimensiones_disponibles: ['Competencia', 'Experiencia', 'Meta'],
    ...sobrescribir,
  }
}

export function precarga(sobrescribir: Partial<Precarga> = {}): Precarga {
  return {
    origen: { tipo: 'dnf', id: 31, fecha: '2026-09-30T10:00:00+00:00' },
    compromisos: [{ dimension: 'Competencia', tipo_elemento: 'Competencia', elemento: { id: 1, nombre: 'Comunicación efectiva' }, nivel_actual: 'C', nivel_objetivo: 'B', descripcion: null }],
    avisos: [],
    ...sobrescribir,
  }
}

export function miPlan(sobrescribir: Partial<MiPlan> = {}): MiPlan {
  return { planes: [pdiAcordado()], que_sigue: [], ...sobrescribir }
}
