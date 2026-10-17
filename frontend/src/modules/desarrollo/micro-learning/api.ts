import { descargarBlob, obtenerPdf } from '../../../shared/api/pdf'
import { http } from '../../../shared/api/httpClient'

export type TipoMicro = 'Video' | 'PDF' | 'Articulo' | 'Quiz'
export type EstadoMicro = 'Borrador' | 'Publicado' | 'Archivado'
/** Filtro de estado del repositorio: `Activos` (Borrador y Publicado) es el valor por defecto. */
export type FiltroEstadoMicro = 'Activos' | EstadoMicro | 'Todos'
export type EstadoProgreso = 'EnProgreso' | 'Completado'
export type EstadoIntento = 'EnCurso' | 'Finalizado'

export const TIPOS_MICRO: TipoMicro[] = ['Video', 'PDF', 'Articulo', 'Quiz']

/** Límites del quiz (spec 3.2). */
export const MAX_PREGUNTAS = 20
export const MIN_OPCIONES = 2
export const MAX_OPCIONES = 5
export const MAX_TITULO = 120
export const MAX_ENUNCIADO = 500
export const MAX_TEXTO_OPCION = 300

export interface CompetenciaMicro {
  id: number
  codigo: string
  nombre: string
  estado: 'Activo' | 'Inactivo'
}

export interface PuntajeQuiz {
  correctas: number
  total: number
}

export interface ProgresoMicro {
  estado: EstadoProgreso
  posicion_segundos: number | null
  fecha_inicio: string
  fecha_completado: string | null
  ultimo_puntaje: PuntajeQuiz | null
}

/** Fila del repositorio (Administrador) o de Mi aprendizaje (colaborador). */
export interface FilaMicro {
  id: number
  codigo: string
  titulo: string
  tipo: TipoMicro
  estado: EstadoMicro
  miniatura_url: string | null
  duracion_minutos: number
  competencias: CompetenciaMicro[]
  apto_conocimientos: boolean
  /** Solo Administrador. */
  completaciones?: number
  puede_eliminarse?: boolean
  /** Solo colaborador. */
  progreso?: ProgresoMicro | null
  /** Solo en Mi aprendizaje. */
  motivo?: 'brecha' | 'iniciado' | null
  completado_en?: string | null
  ultimo_puntaje?: PuntajeQuiz | null
}

export interface OpcionQuiz {
  id: number
  texto: string
  orden: number
  /** Solo la recibe el Administrador. */
  es_correcta?: boolean
}

export interface PreguntaQuiz {
  id: number
  enunciado: string
  orden: number
  opciones: OpcionQuiz[]
}

export interface Micro extends Omit<FilaMicro, 'motivo' | 'completado_en' | 'ultimo_puntaje'> {
  url: string | null
  preguntas?: PreguntaQuiz[]
  /** Solo Administrador. */
  tiene_intentos?: boolean
  created_by?: string | null
  created_at?: string | null
  updated_by?: string | null
  updated_at?: string | null
}

export interface Meta {
  current_page: number
  per_page: number
  total: number
  last_page: number
  from: number | null
  to: number | null
}

export interface PaginaMicros {
  data: FilaMicro[]
  meta: Meta
}

export interface FiltrosMicro {
  q?: string
  tipo?: TipoMicro
  estado?: FiltroEstadoMicro
  competencia_id?: number
  page?: number
  per_page?: number
}

export interface OpcionesMicro {
  competencias_activas: { id: number; codigo: string; nombre: string }[]
  competencias: CompetenciaMicro[]
}

export interface OpcionBorrador {
  texto: string
  es_correcta: boolean
}

export interface PreguntaBorrador {
  enunciado: string
  opciones: OpcionBorrador[]
}

/** Cuerpo para crear o reemplazar un micro-contenido. */
export interface DatosMicro {
  titulo: string
  tipo: TipoMicro
  duracion_minutos: number
  url: string | null
  miniatura_url: string | null
  competencia_ids: number[]
  preguntas?: PreguntaBorrador[] | null
}

export interface RespuestaDada {
  pregunta_id: number
  opcion_id: number
  es_correcta: boolean
}

export interface Intento {
  id: number
  estado: EstadoIntento
  total_preguntas: number
  respondidas: RespuestaDada[]
  preguntas: PreguntaQuiz[]
}

export interface ResultadoRespuesta {
  es_correcta: boolean
  opcion_correcta_id: number | null
  completado_ahora: boolean
  intento: {
    estado: EstadoIntento
    respondidas: RespuestaDada[]
    puntaje: number | null
    total_preguntas: number
  }
}

export interface ResumenAprendizaje {
  completados: number
  en_progreso: number
  horas_acumuladas: number
  certificados: number
}

export interface MiAprendizaje {
  resumen: ResumenAprendizaje
  asignados: FilaMicro[]
  completados: FilaMicro[]
  avisos?: string[]
}

export interface EntradaHistorialMicro {
  id: number
  estado_anterior: EstadoMicro | null
  estado_nuevo: EstadoMicro
  usuario: string | null
  fecha: string | null
}

export interface PaginaHistorialMicro {
  data: EntradaHistorialMicro[]
  meta: Meta
}

const BASE = '/api/microcontenidos'

export async function listarMicros(filtros: FiltrosMicro): Promise<PaginaMicros> {
  const params: Record<string, string | number> = {}
  if (filtros.q) params.q = filtros.q
  if (filtros.tipo) params.tipo = filtros.tipo
  if (filtros.estado) params.estado = filtros.estado
  if (filtros.competencia_id) params.competencia_id = filtros.competencia_id
  if (filtros.page) params.page = filtros.page
  if (filtros.per_page) params.per_page = filtros.per_page
  const response = await http.get<PaginaMicros>(BASE, { params })
  return response.data
}

export async function obtenerOpciones(): Promise<OpcionesMicro> {
  const response = await http.get<{ data: OpcionesMicro }>(`${BASE}/opciones`)
  return response.data.data
}

export async function obtenerMicro(id: number): Promise<Micro> {
  const response = await http.get<{ data: Micro }>(`${BASE}/${id}`)
  return response.data.data
}

export async function crearMicro(datos: DatosMicro): Promise<Micro> {
  const response = await http.post<{ data: Micro }>(BASE, datos)
  return response.data.data
}

export async function actualizarMicro(id: number, datos: DatosMicro): Promise<Micro> {
  const response = await http.put<{ data: Micro }>(`${BASE}/${id}`, datos)
  return response.data.data
}

export async function cambiarEstadoMicro(id: number, estado: 'Publicado' | 'Archivado'): Promise<Micro> {
  const response = await http.patch<{ data: Micro }>(`${BASE}/${id}/estado`, { estado })
  return response.data.data
}

export async function eliminarMicro(id: number): Promise<void> {
  await http.delete(`${BASE}/${id}`)
}

export async function listarHistorialMicro(id: number, page: number): Promise<PaginaHistorialMicro> {
  const response = await http.get<PaginaHistorialMicro>(`${BASE}/${id}/historial`, { params: { page, per_page: 5 } })
  return response.data
}

export async function obtenerMiAprendizaje(): Promise<MiAprendizaje> {
  const response = await http.get<{ data: MiAprendizaje }>(`${BASE}/mi-aprendizaje`)
  return response.data.data
}

export async function guardarAvance(id: number, posicionSegundos: number | null): Promise<void> {
  await http.put(`${BASE}/${id}/progreso`, posicionSegundos === null ? {} : { posicion_segundos: posicionSegundos })
}

export async function completarMicro(id: number): Promise<void> {
  await http.post(`${BASE}/${id}/completar`)
}

export async function iniciarIntento(id: number, reiniciar = false): Promise<Intento> {
  const response = await http.post<{ data: Intento }>(`${BASE}/${id}/intentos`, reiniciar ? { reiniciar: true } : {})
  return response.data.data
}

export async function responderPregunta(intentoId: number, preguntaId: number, opcionId: number): Promise<ResultadoRespuesta> {
  const response = await http.post<{ data: ResultadoRespuesta }>(`${BASE}/intentos/${intentoId}/respuestas`, {
    pregunta_id: preguntaId,
    opcion_id: opcionId,
  })
  return response.data.data
}

/** Descarga el certificado PDF de un contenido completado con el cliente compartido (el Bearer no viaja en un enlace). */
export async function descargarCertificado(id: number, codigo: string): Promise<void> {
  const pdf = await obtenerPdf(`${BASE}/${id}/certificado`)
  descargarBlob(pdf.blob, pdf.nombreDeArchivo ?? `certificado-${codigo}.pdf`)
}
