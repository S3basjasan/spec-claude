import { http } from '../../../shared/api/httpClient'

export type TipoAccion = 'Presencial' | 'Virtual' | 'ELearning' | 'OJT' | 'Codesarrollo' | 'Mentoring'
export type EstadoActividad = 'Programada' | 'EnCurso' | 'Finalizada' | 'Cancelada'
export type EstadoPlan = 'Borrador' | 'Revision' | 'Aprobado' | 'Ejecutando' | 'Cerrado'
export type Asistencia = 'Pendiente' | 'Presente' | 'Ausente' | 'Justificado'
/** Asistencia que se puede registrar (Pendiente es la ausencia de registro). */
export type AsistenciaRegistrada = Exclude<Asistencia, 'Pendiente'>
export type MotivoBloqueo = 'actividad_programada' | 'actividad_cancelada' | 'plan_cerrado'
export type VistaAdmin = 'actividades' | 'calificaciones'
export type Agrupacion = 'actividad' | 'participante'

export const TIPOS_ACCION: TipoAccion[] = ['Presencial', 'Virtual', 'ELearning', 'OJT', 'Codesarrollo', 'Mentoring']
export const ESTADOS_ACTIVIDAD: EstadoActividad[] = ['Programada', 'EnCurso', 'Finalizada', 'Cancelada']
export const ASISTENCIAS_REGISTRABLES: AsistenciaRegistrada[] = ['Presente', 'Ausente', 'Justificado']
export const TIPOS_CON_ENLACE: TipoAccion[] = ['Virtual', 'ELearning']
export const MAX_LUGAR = 500
export const MAX_OBSERVACIONES = 1000
export const CALIFICACION_MIN = 1
export const CALIFICACION_MAX = 5

export interface Meta {
  page: number
  per_page: number
  total: number
  last_page: number
}

export interface Referencia {
  id: number
  nombre: string
}

export interface PlanResumen {
  id: number
  codigo: string
  nombre: string
  estado: EstadoPlan
}

/** Fila de la tabla de actividades (Administrador). */
export interface ActividadResumen {
  id: number
  codigo: string
  accion_id: number
  nombre: string
  tipo: TipoAccion | null
  plan: PlanResumen
  fecha_inicio: string | null
  fecha_fin: string | null
  lugar_enlace: string
  formador: string | null
  departamento: Referencia | null
  capacidad: number | null
  inscritos: number
  estado: EstadoActividad
  asistencia_pendiente: boolean
}

export interface ResumenAsistencia {
  presentes: number
  ausentes: number
  justificados: number
  pendientes: number
  calificados: number
  promedio: number | null
}

export interface Participante {
  colaborador_id: number
  nombre: string
  asistencia: Asistencia
  calificacion: number | null
  observaciones: string | null
}

export interface Auditoria {
  creado_por: string
  creado_en: string | null
  modificado_por: string
  modificado_en: string | null
}

export interface ActividadDetalle extends ActividadResumen {
  competencias: Referencia[]
  horas: number | null
  version: number
  puede_editar: boolean
  puede_registrar_asistencia: boolean
  motivo_bloqueo: MotivoBloqueo | null
  resumen_asistencia: ResumenAsistencia
  participantes: Participante[]
  auditoria: Auditoria
}

export interface AccionDisponible {
  accion_id: number
  nombre: string
  tipo: TipoAccion | null
  plan: { id: number; codigo: string; nombre: string }
  competencias: Referencia[]
  fecha_inicio: string | null
  fecha_fin: string | null
  formador: string | null
  capacidad: number | null
  inscritos: number
}

export interface PlanOpcion {
  id: number
  codigo: string
  nombre: string
  anio: number
  estado: EstadoPlan
}

export interface OpcionesActividades {
  departamentos: Referencia[]
  competencias: Referencia[]
  planes: PlanOpcion[]
}

export interface FiltrosActividades {
  q?: string
  tipo?: TipoAccion
  estado?: EstadoActividad
  departamento_id?: number
  competencia_id?: number
  plan_id?: number
  fecha_desde?: string
  fecha_hasta?: string
  page?: number
  per_page?: number
}

export interface Pagina<T> {
  data: T[]
  meta: Meta
}

export interface DatosActividad {
  plan_accion_id?: number
  lugar_enlace: string
  departamento_id: number | null
}

/** Un registro del guardado conjunto de asistencia. */
export interface RegistroAsistencia {
  colaborador_id: number
  asistencia: Asistencia
  calificacion?: number | null
  observaciones?: string | null
}

export interface EntradaHistorial {
  id: number
  fecha: string | null
  usuario: string
  colaborador_id: number
  participante: string
  asistencia_anterior: Asistencia | null
  asistencia_nueva: Asistencia | null
  calificacion_anterior: number | null
  calificacion_nueva: number | null
}

export interface FiltrosCalificaciones {
  agrupar: Agrupacion
  fecha_desde?: string
  fecha_hasta?: string
  tipo?: TipoAccion
  departamento_id?: number
  plan_id?: number
  colaborador_id?: number
  page?: number
  per_page?: number
}

export interface CalificacionActividad {
  id: number
  codigo: string
  nombre: string
  tipo: TipoAccion | null
  fecha_inicio: string | null
  fecha_fin: string | null
  calificados: number
  promedio: number | null
}

export interface CalificacionParticipante {
  colaborador_id: number
  nombre: string
  actividades_calificadas: number
  promedio: number | null
}

export interface DetalleCalificacionActividad {
  id: number
  codigo: string
  nombre: string
  tipo: TipoAccion | null
  fecha_inicio: string | null
  asistencia: Asistencia
  calificacion: number | null
}

export interface DetalleParticipante extends CalificacionParticipante {
  actividades: DetalleCalificacionActividad[]
}

/** Tarjeta del colaborador: nunca trae observaciones. */
export interface ActividadColaborador {
  id: number
  codigo: string
  nombre: string
  tipo: TipoAccion | null
  fecha_inicio: string | null
  fecha_fin: string | null
  lugar_enlace: string
  formador: string | null
  estado: EstadoActividad
  asistencia: Asistencia
  calificacion: number | null
}

export interface ResumenColaborador {
  inscritas: number
  finalizadas: number
  asistencias_registradas: number
  presentes: number
  asistencia_porcentaje: number | null
  calificadas: number
  calificacion_promedio: number | null
}

export interface MisActividades {
  resumen: ResumenColaborador
  proximas: ActividadColaborador[]
  historial: ActividadColaborador[]
}

const BASE = '/api/actividades'

function limpiar<T extends object>(filtros: T): Record<string, string | number> {
  const params: Record<string, string | number> = {}
  for (const [clave, valor] of Object.entries(filtros)) {
    if (valor !== undefined && valor !== '') params[clave] = valor as string | number
  }
  return params
}

export async function listarActividades(filtros: FiltrosActividades): Promise<Pagina<ActividadResumen>> {
  const { data } = await http.get<Pagina<ActividadResumen>>(BASE, { params: limpiar(filtros) })
  return data
}

export async function obtenerActividad(id: number): Promise<ActividadDetalle> {
  const { data } = await http.get<{ data: ActividadDetalle }>(`${BASE}/${id}`)
  return data.data
}

export async function listarAccionesDisponibles(filtros: { plan_id?: number; q?: string; page?: number; per_page?: number }): Promise<Pagina<AccionDisponible>> {
  const { data } = await http.get<Pagina<AccionDisponible>>(`${BASE}/acciones-disponibles`, { params: limpiar(filtros) })
  return data
}

export async function obtenerOpciones(): Promise<OpcionesActividades> {
  const { data } = await http.get<{ data: OpcionesActividades }>(`${BASE}/opciones`)
  return data.data
}

export async function crearActividad(datos: DatosActividad): Promise<ActividadDetalle> {
  const { data } = await http.post<{ data: ActividadDetalle }>(BASE, datos)
  return data.data
}

export async function actualizarActividad(id: number, datos: DatosActividad): Promise<ActividadDetalle> {
  const { data } = await http.put<{ data: ActividadDetalle }>(`${BASE}/${id}`, datos)
  return data.data
}

export async function registrarAsistencia(id: number, version: number, registros: RegistroAsistencia[]): Promise<ActividadDetalle> {
  const { data } = await http.put<{ data: ActividadDetalle }>(`${BASE}/${id}/asistencia`, { version, registros })
  return data.data
}

export async function listarHistorial(id: number, page: number): Promise<Pagina<EntradaHistorial>> {
  const { data } = await http.get<Pagina<EntradaHistorial>>(`${BASE}/${id}/historial`, { params: { page, per_page: 10 } })
  return data
}

export async function listarCalificacionesPorActividad(filtros: Omit<FiltrosCalificaciones, 'agrupar' | 'colaborador_id'>): Promise<Pagina<CalificacionActividad>> {
  const { data } = await http.get<Pagina<CalificacionActividad>>(`${BASE}/calificaciones`, { params: limpiar({ ...filtros, agrupar: 'actividad' }) })
  return data
}

export async function listarCalificacionesPorParticipante(filtros: Omit<FiltrosCalificaciones, 'agrupar' | 'colaborador_id'>): Promise<Pagina<CalificacionParticipante>> {
  const { data } = await http.get<Pagina<CalificacionParticipante>>(`${BASE}/calificaciones`, { params: limpiar({ ...filtros, agrupar: 'participante' }) })
  return data
}

export async function obtenerDetalleParticipante(colaboradorId: number, filtros: Omit<FiltrosCalificaciones, 'agrupar' | 'colaborador_id' | 'page' | 'per_page'>): Promise<DetalleParticipante> {
  const { data } = await http.get<{ data: DetalleParticipante }>(`${BASE}/calificaciones`, {
    params: limpiar({ ...filtros, agrupar: 'participante', colaborador_id: colaboradorId }),
  })
  return data.data
}

export async function obtenerMisActividades(): Promise<MisActividades> {
  const { data } = await http.get<{ data: MisActividades }>(`${BASE}/mis-actividades`)
  return data.data
}
