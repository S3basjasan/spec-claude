import { http } from '../../../shared/api/httpClient'

export type EstadoPlan = 'Borrador' | 'Revision' | 'Aprobado' | 'Ejecutando' | 'Cerrado'
export type EstadoAccion = 'Programada' | 'EnCurso' | 'Finalizada' | 'Cancelada'
export type TipoAccion = 'Presencial' | 'Virtual' | 'ELearning' | 'OJT' | 'Codesarrollo' | 'Mentoring'
export type TipoHistorial = 'Estado' | 'Participante' | 'Cancelacion' | 'Aprobador'
export type CampoFaltante =
  | 'acciones'
  | 'aprobador'
  | 'competencias'
  | 'tipo'
  | 'fechas'
  | 'duracion_horas'
  | 'formador'
  | 'capacidad_maxima'
  | 'costo'
  | 'participantes'

export const ESTADOS_PLAN: EstadoPlan[] = ['Borrador', 'Revision', 'Aprobado', 'Ejecutando', 'Cerrado']
export const TIPOS_ACCION: TipoAccion[] = ['Presencial', 'Virtual', 'ELearning', 'OJT', 'Codesarrollo', 'Mentoring']

export interface Referencia {
  id: number
  nombre: string
}

export interface CompetenciaAccion {
  id: number
  codigo: string | null
  nombre: string
  activa: boolean
}

export interface ParticipanteAccion {
  colaborador_id: number
  nombre: string
  vinculo_vigente: boolean
}

export interface Accion {
  id: number
  plan_id: number
  curso_id: number | null
  nombre: string
  tipo: TipoAccion | null
  fecha_inicio: string | null
  fecha_fin: string | null
  duracion_horas: number | null
  formador: string | null
  capacidad_maxima: number | null
  costo: number | null
  cancelada: boolean
  motivo_cancelacion: string | null
  estado_accion: EstadoAccion
  competencias: CompetenciaAccion[]
  participantes: ParticipanteAccion[]
  completa: boolean
  faltantes: CampoFaltante[]
}

export interface Puede {
  editar: boolean
  enviar_revision: boolean
  aprobar: boolean
  devolver: boolean
  iniciar: boolean
  cerrar: boolean
  reasignar_aprobador: boolean
  cambiar_participantes: boolean
  cancelar_acciones: boolean
}

export interface AvisoCruce {
  colaborador_id: number
  nombre: string
  accion_id: number
  con_accion_id: number
  con_plan_id: number
}

export interface Plan {
  id: number
  codigo: string
  nombre: string
  anio: number
  descripcion: string | null
  estado: EstadoPlan
  aprobador: Referencia | null
  origen_dnf: { id: number; codigo: string | null } | null
  fecha_aprobacion: string | null
  cantidad_acciones: number
  costo_total: number
  horas_totales: number
  creado_por: Referencia | null
  creado_en: string | null
  modificado_por: Referencia | null
  modificado_en: string | null
  acciones: Accion[]
  avisos_cruce: AvisoCruce[]
  puede: Puede
}

export interface FilaPlan {
  id: number
  codigo: string
  nombre: string
  anio: number
  estado: EstadoPlan
  cantidad_acciones: number
  costo_total: number
  aprobador: Referencia | null
  puede: Puede
}

export interface Meta {
  page: number
  per_page: number
  total: number
  last_page: number
}

export interface PaginaPlanes {
  data: FilaPlan[]
  meta: Meta
}

export interface FiltrosPlanes {
  anio?: number
  estado?: EstadoPlan
  q?: string
  page?: number
  per_page?: number
}

export interface OpcionesPlan {
  aprobadores: Referencia[]
  tipos: TipoAccion[]
  competencias: { id: number; codigo: string; nombre: string }[]
}

export interface ColaboradorBuscado {
  id: number
  codigo: string
  nombre: string
}

export interface PaginaColaboradores {
  data: ColaboradorBuscado[]
  meta: Meta
}

export interface CursoCatalogo {
  id: number
  nombre: string
  tipo_sugerido: TipoAccion | null
  formador: string | null
  duracion_horas: number
  competencia_ids: number[]
}

/** Cuerpo de una acción al guardar el plan en Borrador. */
export interface AccionBorrador {
  id?: number
  nombre: string
  curso_id: number | null
  tipo: TipoAccion | null
  fecha_inicio: string | null
  fecha_fin: string | null
  duracion_horas: number | null
  formador: string | null
  capacidad_maxima: number | null
  costo: number | null
  competencia_ids: number[]
  colaborador_ids: number[]
}

export interface PlanBorrador {
  nombre: string
  anio: number
  descripcion: string | null
  aprobador_id: number | null
  acciones: AccionBorrador[]
}

export interface FiltrosAcciones {
  estado?: EstadoPlan
  tipo?: TipoAccion
  competencia_id?: number
  fecha_desde?: string
  fecha_hasta?: string
  anio?: number
  plan_id?: number
  page?: number
  per_page?: number
}

export interface FilaAccion {
  id: number
  plan: { id: number; codigo: string; nombre: string; estado: EstadoPlan }
  nombre: string
  competencias: Referencia[]
  tipo: TipoAccion | null
  fecha_inicio: string | null
  fecha_fin: string | null
  formador: string | null
  inscritos: number
  capacidad_maxima: number | null
  costo: number | null
  cancelada: boolean
  motivo_cancelacion: string | null
  estado_accion: EstadoAccion
}

export interface PaginaAcciones {
  data: FilaAccion[]
  meta: Meta
}

export interface RenglonHistorial {
  id: number
  tipo: TipoHistorial
  accion_id: number | null
  accion_nombre: string | null
  valor_anterior: string | null
  valor_nuevo: string | null
  motivo: string | null
  usuario: Referencia | null
  fecha: string | null
}

export interface PaginaHistorial {
  data: RenglonHistorial[]
  meta: Meta
}

export interface AccionMiPlan {
  id: number
  plan: { id: number; nombre: string; anio: number }
  nombre: string
  competencias: Referencia[]
  tipo: TipoAccion | null
  fecha_inicio: string | null
  fecha_fin: string | null
  formador: string | null
  duracion_horas: number | null
  estado_accion: EstadoAccion
  motivo_cancelacion: string | null
  completada: boolean
}

export interface MiPlan {
  porcentaje_completado: number
  acciones_asignadas: number
  acciones_completadas: number
  acciones: AccionMiPlan[]
  proximas: AccionMiPlan[]
}

export interface ResultadoParticipantes {
  accion: Accion
  avisos_cruce: AvisoCruce[]
}

/** Transiciones que acepta `PATCH /api/planes/{id}/estado`. */
export type EstadoDestino = 'Revision' | 'Aprobado' | 'Borrador' | 'Ejecutando' | 'Cerrado'

function limpiar<T extends object>(filtros: T): Record<string, unknown> {
  return Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== undefined && valor !== ''))
}

export async function listarPlanes(filtros: FiltrosPlanes): Promise<PaginaPlanes> {
  const { data } = await http.get<PaginaPlanes>('/api/planes', { params: limpiar(filtros) })
  return data
}

export async function obtenerPlan(id: number): Promise<Plan> {
  const { data } = await http.get<{ data: Plan }>(`/api/planes/${id}`)
  return data.data
}

export async function crearPlan(cuerpo: PlanBorrador): Promise<Plan> {
  const { data } = await http.post<{ data: Plan }>('/api/planes', cuerpo)
  return data.data
}

export async function actualizarPlan(id: number, cuerpo: PlanBorrador): Promise<Plan> {
  const { data } = await http.put<{ data: Plan }>(`/api/planes/${id}`, cuerpo)
  return data.data
}

export async function cambiarEstadoPlan(id: number, estado: EstadoDestino, motivo?: string): Promise<Plan> {
  const { data } = await http.patch<{ data: Plan }>(`/api/planes/${id}/estado`, { estado, ...(motivo ? { motivo } : {}) })
  return data.data
}

export async function reasignarAprobador(id: number, aprobadorId: number): Promise<Plan> {
  const { data } = await http.patch<{ data: Plan }>(`/api/planes/${id}/aprobador`, { aprobador_id: aprobadorId })
  return data.data
}

export async function obtenerOpciones(): Promise<OpcionesPlan> {
  const { data } = await http.get<OpcionesPlan>('/api/planes/opciones')
  return data
}

export async function buscarColaboradores(q: string, page = 1, perPage = 20): Promise<PaginaColaboradores> {
  const { data } = await http.get<PaginaColaboradores>('/api/planes/colaboradores', { params: limpiar({ q, page, per_page: perPage }) })
  return data
}

export async function listarCursos(q?: string): Promise<CursoCatalogo[]> {
  const { data } = await http.get<{ data: CursoCatalogo[] }>('/api/planes/cursos', { params: limpiar({ q }) })
  return data.data
}

export async function listarHistorial(id: number, page = 1, perPage = 10): Promise<PaginaHistorial> {
  const { data } = await http.get<PaginaHistorial>(`/api/planes/${id}/historial`, { params: { page, per_page: perPage } })
  return data
}

export async function listarAcciones(filtros: FiltrosAcciones): Promise<PaginaAcciones> {
  const { data } = await http.get<PaginaAcciones>('/api/plan-acciones', { params: limpiar(filtros) })
  return data
}

export async function cambiarParticipantes(accionId: number, colaboradorIds: number[]): Promise<ResultadoParticipantes> {
  const { data } = await http.put<{ data: ResultadoParticipantes }>(`/api/plan-acciones/${accionId}/participantes`, { colaborador_ids: colaboradorIds })
  return data.data
}

export async function cancelarAccion(accionId: number, motivo: string): Promise<Accion> {
  const { data } = await http.patch<{ data: Accion }>(`/api/plan-acciones/${accionId}/cancelar`, { motivo })
  return data.data
}

export async function obtenerMiPlan(): Promise<MiPlan> {
  const { data } = await http.get<{ data: MiPlan }>('/api/planes/mi-plan')
  return data.data
}
