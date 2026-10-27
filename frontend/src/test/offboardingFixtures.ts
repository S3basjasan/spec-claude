import type {
  ContextoDeSalida,
  Cuestionario,
  DetalleDeSalida,
  FilaDeSalida,
  MiDesvinculacion,
  MiEntrevista,
  PaginaDeSalidas,
  ResumenDeSalidas,
  TareaDeSalida,
} from '../modules/gestionPersonal/offboarding/api'

export function fila(cambios: Partial<FilaDeSalida> = {}): FilaDeSalida {
  return {
    id: 5,
    colaborador: { id: 3, codigo: 'EMP-003', nombre: 'Ana Núñez' },
    motivo: 'Renuncia',
    fecha_efectiva: '2026-11-10',
    estado: 'Programada',
    cierre_estado: 'Pendiente',
    cuenta_estado: 'Pendiente',
    entrevista_estado: 'Pendiente',
    tareas_pendientes: 3,
    version: 1,
    ...cambios,
  }
}

export function pagina(filas: FilaDeSalida[] = [fila()], cambios: Partial<PaginaDeSalidas['meta']> = {}): PaginaDeSalidas {
  return {
    data: filas,
    meta: { current_page: 1, per_page: 20, total: filas.length, last_page: 1, from: filas.length ? 1 : null, to: filas.length || null, ...cambios },
  }
}

export function resumen(cambios: Partial<ResumenDeSalidas> = {}): ResumenDeSalidas {
  return { programadas: 2, efectivas: 1, con_incidencia: 1, cierre_pendiente: 1, entrevistas_pendientes: 2, entrevistas_completadas: 3, entrevistas_no_realizadas: 1, ...cambios }
}

export function tarea(cambios: Partial<TareaDeSalida> = {}): TareaDeSalida {
  return { id: 1, orden: 1, tipo: 'Plantilla', nombre: 'Devolver equipos', descripcion: 'Laptop y credenciales', exige_evidencia: true, estado: 'Pendiente', completada_por: null, completada_en: null, respaldos: [], ...cambios }
}

export function detalle(cambios: Partial<DetalleDeSalida> = {}): DetalleDeSalida {
  return {
    id: 5,
    colaborador: { id: 3, codigo: 'EMP-003', nombre: 'Ana Núñez' },
    motivo: { clave: 'Renuncia', etiqueta: 'Renuncia voluntaria' },
    fecha_efectiva: '2026-11-10',
    estado: 'Programada',
    version: 1,
    formalizado: { por: { id: 9, nombre: 'Rosa Vera' }, en: '2026-10-26T15:00:00Z' },
    politica: { version_id: 4, numero: 2 },
    aplicacion: { aplicada_en: null, por_sistema: false, intentos: 0, incidencia: null },
    cuenta_estado: 'Pendiente',
    cierre: { estado: 'Pendiente', por: null, en: null, bloqueos: ['SALIDA_NO_EFECTIVA', 'TAREAS_PENDIENTES', 'ENTREVISTA_SIN_RESOLVER'] },
    cancelacion: null,
    tareas: [
      tarea(),
      tarea({ id: 2, orden: 2, nombre: 'Cerrar accesos', descripcion: null, exige_evidencia: false }),
      tarea({ id: 3, orden: 3, tipo: 'Entrevista', nombre: 'Entrevista de salida', descripcion: null, exige_evidencia: false }),
    ],
    respaldos: [],
    entrevista: { estado: 'Pendiente', version: 1, presentada_en: null, motivo_no_realizada: null, no_realizada_en: null },
    acciones: { cancelar: true, aplicar: false, completar_tareas: true, cerrar: false, no_realizada: true },
    historial: [{ evento: 'Formalizada', actor: { tipo: 'usuario', id: 9, nombre: 'Rosa Vera' }, detalle: null, en: '2026-10-26T15:00:00Z' }],
    ...cambios,
  }
}

export function contexto(cambios: Partial<ContextoDeSalida> = {}): ContextoDeSalida {
  return {
    colaborador: { id: 3, codigo: 'EMP-003', nombre: 'Ana Núñez' },
    hoy: '2026-10-26',
    vinculo_vigente: { id: 11, fecha_ingreso: '2026-01-05', fecha_salida: null },
    salida_existente: null,
    motivos: [
      { clave: 'Renuncia', etiqueta: 'Renuncia voluntaria' },
      { clave: 'Terminacion', etiqueta: 'Terminación por decisión de la organización' },
      { clave: 'FinContrato', etiqueta: 'Finalización de contrato' },
      { clave: 'Jubilacion', etiqueta: 'Jubilación' },
    ],
    politica: {
      situacion: 'Vigente',
      numero: 2,
      tareas: [
        { orden: 1, nombre: 'Devolver equipos', descripcion: 'Laptop y credenciales', exige_evidencia: true },
        { orden: 2, nombre: 'Cerrar accesos', descripcion: null, exige_evidencia: false },
      ],
    },
    ...cambios,
  }
}

export const cuestionario: Cuestionario = {
  version: 1,
  escala: { '1': 'Muy insatisfecho', '2': 'Insatisfecho', '3': 'Ni satisfecho ni insatisfecho', '4': 'Satisfecho', '5': 'Muy satisfecho' },
  dimensiones: {
    'DIM-DESARROLLO': { nombre: 'Desarrollo profesional', pregunta: '¿Qué tan satisfecho estuvo con las oportunidades de crecimiento y desarrollo profesional?' },
    'DIM-GENERAL': { nombre: 'Experiencia general', pregunta: '¿Qué tan satisfecho estuvo, en términos generales, con su experiencia laboral?' },
  },
  categorias: {
    'CAT-DESARROLLO': { nombre: 'Desarrollo profesional', descripcion: 'Oportunidades de crecimiento, capacitación o proyección laboral.' },
    'CAT-COMPENSACION': { nombre: 'Compensación', descripcion: 'Percepción sobre remuneración y beneficios laborales.' },
  },
  abiertas: {
    'ABI-01': '¿Cuáles fueron los principales factores que influyeron en su decisión de desvincularse?',
    'ABI-06': '¿Desea compartir alguna observación adicional?',
  },
}

export function miDesvinculacion(cambios: Partial<MiDesvinculacion> = {}): MiDesvinculacion {
  return {
    id: 5,
    motivo: { clave: 'Renuncia', etiqueta: 'Renuncia voluntaria' },
    fecha_efectiva: '2026-11-10',
    estado: 'Programada',
    tareas: [
      { orden: 1, tipo: 'Plantilla', nombre: 'Devolver equipos', descripcion: 'Laptop y credenciales', estado: 'Pendiente' },
      { orden: 2, tipo: 'Entrevista', nombre: 'Entrevista de salida', descripcion: null, estado: 'Pendiente' },
    ],
    entrevista: { estado: 'Pendiente', version: 1, responder_disponible: true, cuestionario_version: 1 },
    ...cambios,
  }
}

export function miEntrevista(cambios: Partial<MiEntrevista> = {}): MiEntrevista {
  return {
    estado: 'Pendiente',
    version: 1,
    cerrada: false,
    presentada_en: null,
    cuestionario,
    respuestas: { valoraciones: {}, categorias: [], abiertas: {} },
    ...cambios,
  }
}
