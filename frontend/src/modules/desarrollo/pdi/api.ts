import { http } from '../../../shared/api/httpClient'

export type TipoPdi = 'Mejora' | 'Carrera' | 'Talento'
export type EstadoPdi = 'Borrador' | 'Acordado' | 'Cerrado'
export type DimensionPdi = 'Competencia' | 'Conocimiento' | 'Experiencia' | 'Meta'
export type TipoAccion = 'Curso' | 'Mentoring' | 'Coaching' | 'Autodesarrollo' | 'Codesarrollo' | 'NuevaResponsabilidad' | 'Otra'
export type EstadoAccion = 'Pendiente' | 'EnCurso' | 'Cumplida' | 'Cancelada'
export type EstadoHito = 'Pendiente' | 'Realizado'
export type ResultadoCompromiso = 'Cerrada' | 'Parcial' | 'NoCerrada'
export type ResultadoPdi = 'BrechaCerrada' | 'Parcial' | 'NoCerrada'
export type Grado = 'A' | 'B' | 'C' | 'D'
export type ClavePermiso =
  | 'desarrollo.pdi.gestionar'
  | 'desarrollo.pdi.acordar'
  | 'desarrollo.pdi.seguimiento'
  | 'desarrollo.pdi.vincular'
  | 'desarrollo.pdi.cerrar'
  | 'desarrollo.pdi.gantt'
  | 'desarrollo.pdi.ver'
  | 'desarrollo.pdi.ver_propio'
  | 'desarrollo.pdi.aceptar_propio'
export type PestanaLista = 'lista' | 'gantt'
export type PestanaDetalle = 'resumen' | 'compromisos' | 'hitos' | 'historial'

export const TIPOS_PDI: TipoPdi[] = ['Mejora', 'Carrera', 'Talento']
export const ESTADOS_PDI: EstadoPdi[] = ['Borrador', 'Acordado', 'Cerrado']
export const TIPOS_ACCION: TipoAccion[] = ['Curso', 'Mentoring', 'Coaching', 'Autodesarrollo', 'Codesarrollo', 'NuevaResponsabilidad', 'Otra']
export const TIPOS_VINCULABLES: TipoAccion[] = ['Curso', 'Mentoring', 'Codesarrollo']
export const ESTADOS_ACCION: EstadoAccion[] = ['Pendiente', 'EnCurso', 'Cumplida', 'Cancelada']
export const RESULTADOS_COMPROMISO: ResultadoCompromiso[] = ['Cerrada', 'Parcial', 'NoCerrada']
export const GRADOS: Grado[] = ['A', 'B', 'C', 'D']
export const MAX_COMENTARIO = 1000

export interface Meta {
  page: number
  per_page: number
  total: number
  last_page: number
}

export interface Pagina<T> {
  data: T[]
  meta: Meta
}

export interface Referencia {
  id: number
  nombre: string
}

export interface Vinculo {
  plan_accion_id: number
  plan_codigo: string | null
  plan_nombre: string | null
  accion_nombre: string | null
  actividad_id: number | null
  requiere_revision: boolean
}

export interface Accion {
  id: number
  orden: number
  tipo: TipoAccion
  descripcion: string
  persona_cargo: string | null
  fecha_inicio: string
  fecha_fin: string
  estado: EstadoAccion
  estado_derivado: boolean
  vinculo: Vinculo | null
}

export interface Compromiso {
  id: number
  orden: number
  dimension: DimensionPdi
  tipo_elemento: string | null
  elemento: Referencia | null
  descripcion: string | null
  nivel_actual: Grado | null
  nivel_objetivo: Grado | null
  indicador: string | null
  fecha_inicio: string
  fecha_fin: string
  resultado: ResultadoCompromiso | null
  acciones: Accion[]
}

export interface Hito {
  id: number
  fecha: string
  descripcion: string
  estado: EstadoHito
  observaciones: string | null
  vencido: boolean
}

export interface AccionesPermitidas {
  editar: boolean
  acordar: boolean
  devolver: boolean
  avance: boolean
  vincular: boolean
  cerrar: boolean
  cambiar_responsable: boolean
  aceptar: boolean
  devolver_colaborador: boolean
}

export interface PdiResumen {
  id: number
  codigo: string
  colaborador: { id: number; nombre: string; departamento: string | null }
  tipo: TipoPdi
  estado: EstadoPdi
  objetivo: string
  puesto_objetivo: Referencia | null
  responsable: { id: number; nombre: string; sin_permiso: boolean }
  fecha_inicio: string
  fecha_fin: string
  avance: number | null
  hitos_vencidos: number
  resultado: ResultadoPdi | null
  version: number
}

export interface Pdi extends Omit<PdiResumen, 'resultado'> {
  origen: { tipo: 'dnf' | 'adecuacion'; id: number } | null
  acuerdo_responsable: { fecha: string; usuario: string } | null
  aceptacion_colaborador: string | null
  comentario_colaborador: string | null
  resultado: ResultadoPdi | null
  resultado_etiqueta: string | null
  conclusion: string | null
  creado_por: string
  creado_en: string | null
  cerrado_en: string | null
  compromisos: Compromiso[]
  hitos: Hito[]
  completitud: string[]
  acciones_permitidas: AccionesPermitidas
}

export interface FiltrosLista {
  tipo?: TipoPdi
  estado?: EstadoPdi
  departamento_id?: number
  responsable_id?: number
  fecha_desde?: string
  fecha_hasta?: string
  page?: number
  per_page?: number
}

export interface Kpis {
  borrador: number
  acordado: number
  cerrado: number
  hitos_vencidos: number
}

export interface PaginaDePlanes extends Pagina<PdiResumen> {
  meta: Meta & { kpis: Kpis }
}

export interface HistorialItem {
  id: number
  fecha: string
  usuario: string
  tipo: string
  tipo_etiqueta: string
  referencia_id: number | null
  valor_anterior: string | null
  valor_nuevo: string | null
  motivo: string | null
}

export interface ColaboradorOpcion {
  id: number
  codigo: string
  nombre: string
  departamento: string | null
}

export interface PuestoOpcion {
  id: number
  codigo: string
  nombre: string
}

export interface OpcionesGenerales {
  colaboradores: ColaboradorOpcion[]
  puestos: PuestoOpcion[]
  departamentos: Referencia[]
  responsables: Referencia[]
  competencias: { id: number; codigo: string; nombre: string }[]
  dimensiones_disponibles: DimensionPdi[]
}

export interface OpcionesDeColaborador {
  puesto_objetivo_sugerido: Referencia | null
  plan_abierto: Record<TipoPdi, { id: number; codigo: string } | null>
  dimensiones_disponibles: DimensionPdi[]
}

export interface CompromisoSugerido {
  dimension: DimensionPdi
  tipo_elemento: string | null
  elemento: Referencia | null
  nivel_actual: Grado | null
  nivel_objetivo: Grado | null
  descripcion: string | null
}

export interface Precarga {
  origen: { tipo: 'dnf' | 'adecuacion'; id: number; fecha: string | null } | null
  compromisos: CompromisoSugerido[]
  avisos: { tipo: string; mensaje: string; cantidad: number }[]
}

export interface CuerpoAccion {
  tipo: TipoAccion
  descripcion: string
  persona_cargo: string | null
  fecha_inicio: string
  fecha_fin: string
  plan_accion_id: number | null
}

export interface CuerpoCompromiso {
  dimension: DimensionPdi
  tipo_elemento: string | null
  elemento_id: number | null
  descripcion: string | null
  nivel_actual: Grado | null
  nivel_objetivo: Grado | null
  indicador: string | null
  fecha_inicio: string
  fecha_fin: string
  acciones: CuerpoAccion[]
}

export interface CuerpoPdi {
  colaborador_id: number
  tipo: TipoPdi
  objetivo: string
  puesto_objetivo_id: number | null
  responsable_id: number
  fecha_inicio: string
  fecha_fin: string
  origen: { tipo: 'dnf' | 'adecuacion'; id: number } | null
  compromisos: CuerpoCompromiso[]
  hitos: { fecha: string; descripcion: string }[]
}

export interface AccionVinculable {
  plan_accion_id: number
  plan_id: number
  plan_codigo: string
  plan_nombre: string
  plan_estado: string
  nombre: string
  tipo: string | null
  fecha_inicio: string | null
  fecha_fin: string | null
  formador: string | null
}

export interface ItemQueSigue {
  tipo: 'accion' | 'hito'
  fecha: string
  descripcion: string
  plan_id: number
  plan_codigo: string
  tipo_plan: TipoPdi
  vencido: boolean
  actividad_id: number | null
}

export interface MiPlan {
  planes: Pdi[]
  que_sigue: ItemQueSigue[]
}

export interface ResultadoDeCierre {
  plan: Pdi
  resultado: ResultadoPdi
  resultado_etiqueta: string
  ofrecer_adecuacion: { colaborador_id: number; puesto_objetivo_id: number } | null
}

export interface FilaDeGantt {
  plan: {
    id: number
    codigo: string
    colaborador: { id: number; nombre: string; departamento: string | null }
    tipo: TipoPdi
    estado: EstadoPdi
    responsable: { id: number; nombre: string; sin_permiso: boolean }
    fecha_inicio: string
    fecha_fin: string
    avance: number | null
    hitos_vencidos: number
  }
  acciones: { id: number; compromiso: string; descripcion: string; tipo: TipoAccion; fecha_inicio: string; fecha_fin: string; estado: EstadoAccion }[]
  hitos: Hito[]
}

export interface Gantt extends Pagina<FilaDeGantt> {
  hoy: string
}

export interface FiltrosGantt {
  tipo?: TipoPdi
  responsable_id?: number
  departamento_id?: number
  page?: number
  per_page?: number
}

export interface FilaSinPlan {
  colaborador: { id: number; nombre: string; departamento: string | null }
  origen: 'dnf' | 'adecuacion'
  tipo_sugerido: TipoPdi
  puesto_objetivo: Referencia | null
  brechas: number
}

const PDI = '/api/pdi'

function limpiar(filtros: object): Record<string, string | number> {
  const params: Record<string, string | number> = {}
  for (const [clave, valor] of Object.entries(filtros)) {
    if (valor !== undefined && valor !== '') params[clave] = valor as string | number
  }
  return params
}

export async function obtenerPermisos(): Promise<ClavePermiso[]> {
  const { data } = await http.get<{ data: { claves: ClavePermiso[] } }>(`${PDI}/permisos`)
  return data.data.claves
}

export async function obtenerOpciones(q: string): Promise<OpcionesGenerales> {
  const { data } = await http.get<{ data: OpcionesGenerales }>(`${PDI}/opciones`, { params: q === '' ? {} : { q } })
  return data.data
}

export async function obtenerOpcionesDeColaborador(colaboradorId: number): Promise<OpcionesDeColaborador> {
  const { data } = await http.get<{ data: OpcionesDeColaborador }>(`${PDI}/opciones`, { params: { colaborador_id: colaboradorId } })
  return data.data
}

export async function obtenerPrecarga(colaboradorId: number, tipo: TipoPdi, puestoObjetivoId: number | null): Promise<Precarga> {
  const { data } = await http.get<{ data: Precarga }>(`${PDI}/precarga`, {
    params: limpiar({ colaborador_id: colaboradorId, tipo, puesto_objetivo_id: puestoObjetivoId ?? undefined }),
  })
  return data.data
}

export async function crearPdi(cuerpo: CuerpoPdi): Promise<Pdi> {
  const { data } = await http.post<{ data: Pdi }>(PDI, cuerpo)
  return data.data
}

export async function actualizarPdi(id: number, cuerpo: CuerpoPdi, version: number): Promise<Pdi> {
  const { data } = await http.put<{ data: Pdi }>(`${PDI}/${id}`, { ...cuerpo, version })
  return data.data
}

export async function obtenerPdi(id: number): Promise<Pdi> {
  const { data } = await http.get<{ data: Pdi }>(`${PDI}/${id}`)
  return data.data
}

export async function listarPdi(filtros: FiltrosLista): Promise<PaginaDePlanes> {
  const { data } = await http.get<PaginaDePlanes>(PDI, { params: limpiar(filtros) })
  return data
}

export async function listarHistorial(id: number, page: number): Promise<Pagina<HistorialItem>> {
  const { data } = await http.get<Pagina<HistorialItem>>(`${PDI}/${id}/historial`, { params: { page } })
  return data
}

export async function confirmarAcuerdo(id: number, version: number): Promise<Pdi> {
  const { data } = await http.post<{ data: Pdi }>(`${PDI}/${id}/acuerdo`, { version })
  return data.data
}

export async function aceptarPdi(id: number, version: number): Promise<Pdi> {
  const { data } = await http.post<{ data: Pdi }>(`${PDI}/${id}/aceptar`, { version })
  return data.data
}

export async function devolverDelColaborador(id: number, comentario: string, version: number): Promise<void> {
  await http.post(`${PDI}/${id}/devolver-colaborador`, { comentario, version })
}

export async function devolverABorrador(id: number, motivo: string, version: number): Promise<Pdi> {
  const { data } = await http.post<{ data: Pdi }>(`${PDI}/${id}/devolver`, { motivo, version })
  return data.data
}

export async function cambiarResponsable(id: number, responsableId: number, version: number): Promise<Pdi> {
  const { data } = await http.patch<{ data: Pdi }>(`${PDI}/${id}/responsable`, { responsable_id: responsableId, version })
  return data.data
}

export async function cerrarPdi(
  id: number,
  resultados: { compromiso_id: number; resultado: ResultadoCompromiso }[],
  conclusion: string | null,
  version: number,
): Promise<ResultadoDeCierre> {
  const { data } = await http.post<{ data: ResultadoDeCierre }>(`${PDI}/${id}/cerrar`, { resultados, conclusion, version })
  return data.data
}

export async function registrarAvanceDeAccion(accionId: number, estado: EstadoAccion): Promise<Pdi> {
  const { data } = await http.patch<{ data: Pdi }>(`/api/pdi-acciones/${accionId}/avance`, { estado })
  return data.data
}

export async function registrarAvanceDeHito(hitoId: number, estado: EstadoHito, observaciones: string | null): Promise<Pdi> {
  const { data } = await http.patch<{ data: Pdi }>(`/api/pdi-hitos/${hitoId}`, { estado, observaciones })
  return data.data
}

export async function vincularAccion(accionId: number, planAccionId: number | null, version: number | null): Promise<Pdi> {
  const { data } = await http.put<{ data: Pdi }>(`/api/pdi-acciones/${accionId}/vinculo`, { plan_accion_id: planAccionId, version })
  return data.data
}

export async function listarAccionesVinculables(colaboradorId: number, q: string): Promise<AccionVinculable[]> {
  const { data } = await http.get<{ data: AccionVinculable[] }>(`${PDI}/acciones-vinculables`, { params: limpiar({ colaborador_id: colaboradorId, q }) })
  return data.data
}

export async function obtenerMiPlan(): Promise<MiPlan> {
  const { data } = await http.get<{ data: MiPlan }>(`${PDI}/mi-plan`)
  return data.data
}

export async function obtenerGantt(filtros: FiltrosGantt): Promise<Gantt> {
  const { data } = await http.get<Gantt>(`${PDI}/gantt`, { params: limpiar(filtros) })
  return data
}

export async function listarSinPlan(departamentoId: number | undefined, page: number): Promise<Pagina<FilaSinPlan>> {
  const { data } = await http.get<Pagina<FilaSinPlan>>(`${PDI}/sin-plan`, { params: limpiar({ departamento_id: departamentoId, page }) })
  return data
}
