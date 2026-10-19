import { http } from '../../../shared/api/httpClient'

export type TipoFormacion = 'Presencial' | 'Virtual' | 'ELearning' | 'OJT' | 'Codesarrollo' | 'Mentoring' | 'Microlearning'
export type EstadoActividad = 'Programada' | 'EnCurso' | 'Finalizada' | 'Cancelada'
export type VistaReportes = 'indicadores' | 'detalle' | 'seguimiento' | 'impactos'
export type FiltroEvaluado = 'con' | 'sin'
export type FormatoExportacion = 'xlsx' | 'pdf'
export type Hito = 30 | 60 | 90
export type NivelCompetencia = 'A' | 'B' | 'C' | 'D'

export const TIPOS_FORMACION: TipoFormacion[] = ['Presencial', 'Virtual', 'ELearning', 'OJT', 'Codesarrollo', 'Mentoring', 'Microlearning']
export const HITOS: Hito[] = [30, 60, 90]
export const CALIFICACION_MIN = 1
export const CALIFICACION_MAX = 5
export const MAX_COMENTARIO = 1000
export const MAX_INDICADOR = 150
export const MAX_UNIDAD = 50
export const MAX_JUSTIFICACION = 2000

export interface Meta {
  total: number
  page: number
  per_page: number
  last_page: number
  fuentes_no_disponibles?: string[]
}

export interface Pagina<T> {
  data: T[]
  meta: Meta
}

/** Filtros comunes de los reportes del administrador (todos opcionales; sin fechas rige el año actual). */
export interface FiltrosReportes {
  fecha_desde?: string
  fecha_hasta?: string
  departamento_id?: number
  tipo?: TipoFormacion
  plan_id?: number
}

export interface Indicador {
  disponible: boolean
  valor: number | null
  anterior: number | null
  diferencia: number | null
  variacion_pct: number | null
}

export interface IndicadorPlanCumplido extends Indicador {
  acciones_finalizadas: number
  acciones_validas: number
}

export interface IndicadorTotalHoras extends Indicador {
  horas_actividades: number
  horas_microlearning: number
}

export interface IndicadorSatisfaccion extends Indicador {
  respuestas: number
}

export interface IndicadorBrechas extends Indicador {
  detectadas: number
}

export interface IndicadorRoi extends Indicador {
  acciones_evaluadas: number
}

export interface Grafico<T> {
  disponible: boolean
  datos: T[]
}

export interface HorasDepartamento {
  departamento_id: number | null
  departamento: string
  horas_actividades: number
  horas_microlearning: number
}

export interface PuntoBrechas {
  mes: string
  activas: number
  cerradas: number
}

export interface HorasTipo {
  tipo: TipoFormacion
  horas: number
}

export interface Dashboard {
  periodo: { desde: string; hasta: string }
  periodo_anterior: { desde: string; hasta: string }
  fuentes_no_disponibles: string[]
  indicadores: {
    plan_cumplido: IndicadorPlanCumplido
    total_horas: IndicadorTotalHoras
    satisfaccion: IndicadorSatisfaccion
    brechas_cerradas: IndicadorBrechas
    roi_estimado: IndicadorRoi
  }
  gestion: {
    inversion_por_colaborador: Indicador
    horas_por_colaborador: Indicador
    costo_por_hora: Indicador
  }
  graficos: {
    horas_por_departamento: Grafico<HorasDepartamento>
    evolucion_brechas: Grafico<PuntoBrechas>
    distribucion_por_tipo: Grafico<HorasTipo>
  }
}

export interface OpcionesReportes {
  departamentos: { id: number; nombre: string }[]
  planes: { id: number; codigo: string; nombre: string; anio: number; estado: string }[]
}

export interface FilaDetalle {
  actividad_id: number
  codigo: string
  nombre: string
  tipo: TipoFormacion | null
  estado: EstadoActividad
  fecha_inicio: string | null
  fecha_fin: string | null
  departamento_id: number | null
  departamento: string | null
  asistentes: number
  inscritos: number
  horas_persona: number
  costo: number
  calificacion_promedio: number | null
  satisfaccion_promedio: number | null
  respuestas_satisfaccion: number
}

export interface ComentarioSatisfaccion {
  calificacion: number
  comentario: string
  fecha: string
}

export interface Comentarios {
  promedio: number | null
  respuestas: number
  comentarios: ComentarioSatisfaccion[]
}

export interface HitoSeguimientoAdmin {
  hito: Hito
  vencimiento: string
  respuestas: number
  tasa_respuesta: number | null
  promedio_aplicacion: number | null
  promedio_apoyo: number | null
}

export interface FilaSeguimiento {
  actividad_id: number
  codigo: string
  nombre: string
  fecha_fin: string
  elegibles: number
  hitos: HitoSeguimientoAdmin[]
  alerta_reingreso: boolean
}

export interface Impacto {
  plan_accion_id: number
  indicador: string
  unidad: string
  valor_antes: number
  valor_despues: number
  beneficio_estimado: number
  justificacion: string
  roi: number | null
  creado_por: string | null
  creado_en: string | null
  modificado_por: string | null
  modificado_en: string | null
}

export interface FilaImpacto {
  plan_accion_id: number
  plan_id: number
  plan_codigo: string
  plan_nombre: string
  accion_nombre: string
  tipo: TipoFormacion | null
  fecha_fin: string | null
  costo: number | null
  evaluada: boolean
  roi: number | null
  impacto: Impacto | null
}

export interface DatosImpacto {
  indicador: string
  unidad: string
  valor_antes: number
  valor_despues: number
  beneficio_estimado: number
  justificacion: string
}

export interface ResumenProgreso {
  formaciones_completadas: number
  competencias_adquiridas: number
  horas_acumuladas: number
  certificados: number
}

export interface EvolucionCompetencia {
  competencia_id: number
  competencia: string
  nivel_primera: NivelCompetencia | null
  nivel_actual: NivelCompetencia
  nivel_requerido: NivelCompetencia
  solo_actual: boolean
}

export interface Certificado {
  microcontenido_id: number
  titulo: string
  fecha: string
  descarga: string
}

export interface ProximaAccion {
  accion_id: number
  tipo: TipoFormacion | null
  nombre: string
  fecha_inicio: string | null
  fecha_fin: string
  competencias: string[]
}

export interface EncuestaPendiente {
  actividad_id: number
  codigo: string
  nombre: string
  fecha_fin: string
  vence: string
}

export interface SeguimientoPendiente extends EncuestaPendiente {
  hito: Hito
}

export interface MiProgreso {
  resumen: ResumenProgreso
  evolucion_competencias: EvolucionCompetencia[]
  certificados: Certificado[]
  proximas_acciones: ProximaAccion[]
  pendientes: { encuestas: EncuestaPendiente[]; seguimientos: SeguimientoPendiente[] }
  fuentes_no_disponibles: string[]
}

export interface RespuestaEncuesta {
  actividad_id: number
  calificacion: number
  comentario: string | null
  fecha: string
}

export interface DatosEncuesta {
  actividad_id: number
  calificacion: number
  comentario?: string
}

export interface DatosSeguimiento {
  actividad_id: number
  hito: Hito
  aplicacion: number
  apoyo: number
  comentario?: string
}

export interface ArchivoExportado {
  blob: Blob
  nombreDeArchivo: string
}

const BASE = '/api/reportes'

function limpiar<T extends object>(filtros: T): Record<string, string | number> {
  const params: Record<string, string | number> = {}
  for (const [clave, valor] of Object.entries(filtros)) {
    if (valor !== undefined && valor !== '') params[clave] = valor as string | number
  }
  return params
}

export async function obtenerOpciones(): Promise<OpcionesReportes> {
  const { data } = await http.get<{ data: OpcionesReportes }>(`${BASE}/opciones`)
  return data.data
}

export async function obtenerDashboard(filtros: FiltrosReportes): Promise<Dashboard> {
  const { data } = await http.get<{ data: Dashboard }>(`${BASE}/dashboard`, { params: limpiar(filtros) })
  return data.data
}

export async function listarDetalle(filtros: FiltrosReportes & { q?: string; page?: number; per_page?: number }): Promise<Pagina<FilaDetalle>> {
  const { data } = await http.get<Pagina<FilaDetalle>>(`${BASE}/detalle`, { params: limpiar(filtros) })
  return data
}

export async function obtenerComentarios(actividadId: number, page: number): Promise<{ data: Comentarios; meta: Meta }> {
  const { data } = await http.get<{ data: Comentarios; meta: Meta }>(`${BASE}/actividades/${actividadId}/satisfaccion`, { params: { page, per_page: 10 } })
  return data
}

export async function listarSeguimiento(filtros: FiltrosReportes & { page?: number; per_page?: number }): Promise<Pagina<FilaSeguimiento>> {
  const { data } = await http.get<Pagina<FilaSeguimiento>>(`${BASE}/seguimiento`, { params: limpiar(filtros) })
  return data
}

export async function listarImpactos(filtros: { plan_id?: number; evaluado?: FiltroEvaluado; page?: number; per_page?: number }): Promise<Pagina<FilaImpacto>> {
  const { data } = await http.get<Pagina<FilaImpacto>>(`${BASE}/impactos`, { params: limpiar(filtros) })
  return data
}

export async function registrarImpacto(planAccionId: number, datos: DatosImpacto): Promise<Impacto> {
  const { data } = await http.put<{ data: Impacto }>(`${BASE}/impactos/${planAccionId}`, datos)
  return data.data
}

export async function obtenerMiProgreso(): Promise<MiProgreso> {
  const { data } = await http.get<{ data: MiProgreso }>(`${BASE}/mi-progreso`)
  return data.data
}

export async function responderEncuesta(datos: DatosEncuesta): Promise<RespuestaEncuesta> {
  const { data } = await http.post<{ data: RespuestaEncuesta }>(`${BASE}/encuestas`, datos)
  return data.data
}

export async function responderSeguimiento(datos: DatosSeguimiento): Promise<void> {
  await http.post(`${BASE}/seguimientos`, datos)
}

function nombreDe(contentDisposition: unknown): string | null {
  if (typeof contentDisposition !== 'string') return null
  const coincidencia = /filename="?([^";]+)"?/i.exec(contentDisposition)
  return coincidencia ? coincidencia[1] : null
}

/** Con `responseType: 'blob'` un error JSON llega como Blob: se lee para que `toApiError` pueda interpretarlo. */
async function leerErrorComoJson(error: unknown): Promise<unknown> {
  const respuesta = (error as { response?: { data?: unknown } } | null)?.response
  if (respuesta && respuesta.data instanceof Blob) {
    try {
      respuesta.data = JSON.parse(await respuesta.data.text())
    } catch {
      respuesta.data = {}
    }
  }
  return error
}

/** Exporta con los filtros aplicados; el Bearer viaja por el cliente compartido, nunca en un enlace. */
export async function exportarReportes(formato: FormatoExportacion, filtros: FiltrosReportes): Promise<ArchivoExportado> {
  try {
    const respuesta = await http.get<Blob>(`${BASE}/exportar`, { params: limpiar({ ...filtros, formato }), responseType: 'blob', headers: { Accept: '*/*' } })
    return { blob: respuesta.data, nombreDeArchivo: nombreDe(respuesta.headers['content-disposition']) ?? `reportes.${formato}` }
  } catch (error) {
    throw await leerErrorComoJson(error)
  }
}

/** Descarga el certificado de un micro-contenido completado (PDF) con el endpoint de Micro-Learning. */
export async function descargarCertificado(rutaDescarga: string): Promise<ArchivoExportado> {
  try {
    const respuesta = await http.get<Blob>(rutaDescarga, { responseType: 'blob', headers: { Accept: 'application/pdf, application/json' } })
    return { blob: respuesta.data, nombreDeArchivo: nombreDe(respuesta.headers['content-disposition']) ?? 'certificado.pdf' }
  } catch (error) {
    throw await leerErrorComoJson(error)
  }
}
