import type { DetalleActuacion, FilaActuacion, MiSolicitud, PaginaActuaciones, ResumenActuaciones } from '../modules/gestionPersonal/movimientos/api'

export function fila(cambios: Partial<FilaActuacion> = {}): FilaActuacion {
  return {
    id: 1,
    tipo: 'Permiso',
    categoria: 'Novedad',
    estado: 'Presentada',
    version: 1,
    colaborador: { id: 3, codigo: 'EMP-003', nombre: 'Ana Núñez' },
    presentada_en: '2026-10-21T15:00:00Z',
    fecha_presentacion: '2026-10-21',
    presentada_por: { id: 9, nombre: 'Rosa Vera' },
    en_nombre_de_colaborador: false,
    periodo: { inicio: '2026-10-26', fin: '2026-10-27' },
    fecha_efectiva: null,
    aplicacion_estado: null,
    ...cambios,
  }
}

export function pagina(filas: FilaActuacion[] = [fila()], cambios: Partial<PaginaActuaciones['meta']> = {}): PaginaActuaciones {
  return {
    data: filas,
    meta: { current_page: 1, per_page: 20, total: filas.length, last_page: 1, from: filas.length ? 1 : null, to: filas.length || null, ...cambios },
  }
}

export function resumen(cambios: Partial<ResumenActuaciones> = {}): ResumenActuaciones {
  return { pendientes: 2, en_revision: 1, programados: 3, aplicados: 4, incidencias: 1, ...cambios }
}

const PARAMETROS = { disponible: true, justificacion_obligatoria: true, documento_obligatorio: false, anticipacion_dias: 2 }

export function detalleDeNovedad(cambios: Partial<DetalleActuacion> = {}): DetalleActuacion {
  return {
    id: 7,
    tipo: 'Permiso',
    categoria: 'Novedad',
    estado: 'Presentada',
    version: 1,
    colaborador: { id: 3, codigo: 'EMP-003', nombre: 'Ana Núñez' },
    presentada: { en: '2026-10-21T15:00:00Z', fecha: '2026-10-21', por: { id: 9, nombre: 'Rosa Vera' }, en_nombre_de_colaborador: false },
    motivo: 'Trámite personal',
    periodo: { inicio: '2026-10-26', fin: '2026-10-27', ocurrida: false },
    revision: null,
    cruce_reconocido: null,
    movimiento: null,
    politica: { version_id: 4, numero: 2, tipo: 'Permisos', parametros: PARAMETROS, reglas_fijas: [{ clave: 'COM-AISLAMIENTO', texto: 'Ninguna política concede acceso entre organizaciones.' }] },
    comprobaciones: [
      { clave: 'vinculo', estado: 'Cumple', texto: 'El colaborador tiene un vínculo laboral vigente.' },
      { clave: 'cruce', estado: 'Cumple', texto: 'No se cruza con otras solicitudes del colaborador.' },
    ],
    puede_aprobar: true,
    motivo_no_aprobable: null,
    cruces: [],
    documentos: [],
    acciones: { revisar: true, rechazar: true, aprobar: true, retirar: false, revisar_ausencia: false, aplicar: false },
    historial: [{ evento: 'Presentada', estado_anterior: null, estado_nuevo: 'Presentada', actor: { tipo: 'usuario', id: 9, nombre: 'Rosa Vera' }, justificacion: null, detalle: null, en: '2026-10-21T15:00:00Z' }],
    ...cambios,
  }
}

export function detalleDeMovimiento(cambios: Partial<DetalleActuacion> = {}): DetalleActuacion {
  return detalleDeNovedad({
    id: 12,
    tipo: 'Ascenso',
    categoria: 'Movimiento',
    motivo: 'Desempeño sostenido en el área',
    periodo: null,
    politica: { version_id: 8, numero: 1, tipo: 'Ascensos', parametros: { disponible: true, anticipacion_dias: 5, documento_adicional_obligatorio: false }, reglas_fijas: [] },
    movimiento: {
      fecha_efectiva: '2026-12-01',
      posicion_actual: { id: 10, puesto: 'Analista de datos', unidad: 'Finanzas' },
      posicion_destino: { id: 11, puesto: 'Jefe de analítica', unidad: 'Finanzas' },
      evaluacion: { estado: 'NoDisponible', motivo: 'No existe una evaluación disponible frente al puesto destino.', contexto: null, consultada_en: null },
      aplicacion: { estado: 'Pendiente', aplicada_en: null, por_sistema: false, intentos: 0, incidencia: null },
    },
    comprobaciones: [
      { clave: 'vinculo', estado: 'Cumple', texto: 'El colaborador tiene un vínculo laboral vigente.' },
      { clave: 'destino', estado: 'Cumple', texto: 'La posición destino admite asignaciones.' },
      { clave: 'evaluacion', estado: 'NoVerificable', texto: 'No existe una evaluación disponible frente al puesto destino.' },
    ],
    puede_aprobar: false,
    motivo_no_aprobable: 'EVALUACION_NO_VERIFICABLE',
    acciones: { revisar: true, rechazar: true, aprobar: false, retirar: false, revisar_ausencia: false, aplicar: false },
    ...cambios,
  })
}

export function miNovedad(cambios: Partial<Extract<MiSolicitud, { categoria: 'Novedad' }>> = {}): Extract<MiSolicitud, { categoria: 'Novedad' }> {
  return {
    id: 21,
    tipo: 'Permiso',
    categoria: 'Novedad',
    estado: 'Presentada',
    version: 1,
    presentada_en: '2026-10-21T15:00:00Z',
    motivo: 'Trámite personal',
    periodo: { inicio: '2026-10-26', fin: '2026-10-27' },
    politica: { numero: 2, requisitos: { justificacion_obligatoria: true, documento_obligatorio: false, anticipacion_dias: 2 } },
    resultado: null,
    retirable: true,
    ...cambios,
  }
}
