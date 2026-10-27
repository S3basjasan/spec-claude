import { http } from '../../../shared/api/httpClient'

export type MotivoDeSalida = 'Renuncia' | 'Terminacion' | 'FinContrato' | 'Jubilacion'
export type EstadoDeSalida = 'Programada' | 'Efectiva' | 'Cancelada' | 'IncidenciaDeAplicacion'
export type EstadoDeCierre = 'Pendiente' | 'Cerrado'
export type EstadoDeCuenta = 'Pendiente' | 'Coordinada' | 'Incidencia'
export type EstadoDeEntrevista = 'Pendiente' | 'EnCurso' | 'Completada' | 'NoRealizada'
export type MotivoDeNoRealizada = 'Declinada' | 'SinRespuesta' | 'SinAcceso'
export type BloqueoDeCierre = 'SALIDA_NO_EFECTIVA' | 'TAREAS_PENDIENTES' | 'ENTREVISTA_SIN_RESOLVER' | 'EVIDENCIA_NO_DISPONIBLE' | 'YA_CERRADO'

export const MOTIVOS: MotivoDeSalida[] = ['Renuncia', 'Terminacion', 'FinContrato', 'Jubilacion']
export const ESTADOS_DE_SALIDA: EstadoDeSalida[] = ['Programada', 'Efectiva', 'Cancelada', 'IncidenciaDeAplicacion']
export const ESTADOS_DE_ENTREVISTA: EstadoDeEntrevista[] = ['Pendiente', 'EnCurso', 'Completada', 'NoRealizada']
export const MOTIVOS_DE_NO_REALIZADA: MotivoDeNoRealizada[] = ['Declinada', 'SinRespuesta', 'SinAcceso']
export const FILTRO_TODOS = 'todos'
export const POR_PAGINA = 20

export interface FilaDeSalida {
  id: number
  colaborador: { id: number; codigo: string; nombre: string }
  motivo: MotivoDeSalida
  fecha_efectiva: string
  estado: EstadoDeSalida
  cierre_estado: EstadoDeCierre
  cuenta_estado: EstadoDeCuenta
  entrevista_estado: EstadoDeEntrevista
  tareas_pendientes: number
  version: number
}

export interface PaginaDeSalidas {
  data: FilaDeSalida[]
  meta: { current_page: number; per_page: number; total: number; last_page: number; from: number | null; to: number | null }
}

export interface ResumenDeSalidas {
  programadas: number
  efectivas: number
  con_incidencia: number
  cierre_pendiente: number
  entrevistas_pendientes: number
  entrevistas_completadas: number
  entrevistas_no_realizadas: number
}

export interface FiltrosDeSalidas {
  estado?: string
  cierre?: string
  motivo?: string
  entrevista?: string
  colaborador_id?: number
  desde?: string
  hasta?: string
  page?: number
}

export interface TareaDeSalida {
  id: number
  orden: number
  tipo: 'Plantilla' | 'Entrevista'
  nombre: string
  descripcion: string | null
  exige_evidencia: boolean
  estado: 'Pendiente' | 'Completada'
  completada_por: { id: number; nombre: string | null } | null
  completada_en: string | null
  respaldos: { id: number; disponible: boolean }[]
}

export interface EventoDeSalida {
  evento: string
  actor: { tipo: 'usuario' | 'sistema'; id: number | null; nombre: string | null }
  detalle: Record<string, unknown> | null
  en: string
}

export interface DetalleDeSalida {
  id: number
  colaborador: { id: number; codigo: string | null; nombre: string | null }
  motivo: { clave: MotivoDeSalida; etiqueta: string }
  fecha_efectiva: string
  estado: EstadoDeSalida
  version: number
  formalizado: { por: { id: number; nombre: string | null }; en: string }
  politica: { version_id: number; numero: number }
  aplicacion: { aplicada_en: string | null; por_sistema: boolean; intentos: number; incidencia: { codigo: string; detalle: string | null } | null }
  cuenta_estado: EstadoDeCuenta
  cierre: { estado: EstadoDeCierre; por: { id: number; nombre: string | null } | null; en: string | null; bloqueos: BloqueoDeCierre[] }
  cancelacion: { razon: string; en: string | null } | null
  tareas: TareaDeSalida[]
  respaldos: { id: number; disponible: boolean }[]
  entrevista: { estado: EstadoDeEntrevista; version: number; presentada_en: string | null; motivo_no_realizada: MotivoDeNoRealizada | null; no_realizada_en: string | null }
  acciones: { cancelar: boolean; aplicar: boolean; completar_tareas: boolean; cerrar: boolean; no_realizada: boolean }
  historial: EventoDeSalida[]
}

export interface TareaDePlantilla {
  orden: number
  nombre: string
  descripcion: string | null
  exige_evidencia: boolean
}

export interface ContextoDeSalida {
  colaborador: { id: number; codigo: string | null; nombre: string | null }
  hoy: string
  vinculo_vigente: { id: number; fecha_ingreso: string; fecha_salida: string | null } | null
  salida_existente: { id: number; estado: EstadoDeSalida; fecha_efectiva: string } | null
  motivos: { clave: MotivoDeSalida; etiqueta: string }[]
  politica: { situacion: 'SinConfigurar' | 'Vigente' | 'NoAdmiteSolicitudes' | 'NoDisponible'; numero: number | null; tareas: TareaDePlantilla[] }
}

export interface Respaldo {
  id: number
  titulo: string
}

export interface ColaboradorOpcion {
  id: number
  codigo: string
  nombre: string
}

export interface DatosDeSalida {
  colaborador_id: number
  motivo: MotivoDeSalida
  fecha_efectiva: string
  documento_ids: number[]
}

export interface Cuestionario {
  version: number
  escala: Record<string, string>
  dimensiones: Record<string, { nombre: string; pregunta: string }>
  categorias: Record<string, { nombre: string; descripcion: string }>
  abiertas: Record<string, string>
}

export interface RespuestasDeEntrevista {
  valoraciones: Record<string, number>
  categorias: string[]
  abiertas: Record<string, string>
}

/** Respuestas originales que ve el Administrador autorizado (solo de entrevistas completadas). */
export interface RespuestasConsultadas {
  estado: EstadoDeEntrevista
  disponible: boolean
  cuestionario_version: number
  presentada_en?: string | null
  cuestionario?: Cuestionario
  respuestas: { valoraciones: Record<string, { dimension: string; valor: number }>; categorias: { id: string; nombre: string }[]; abiertas: Record<string, string> } | null
}

export interface MiDesvinculacion {
  id: number
  motivo: { clave: MotivoDeSalida; etiqueta: string }
  fecha_efectiva: string
  estado: 'Programada' | 'Efectiva'
  tareas: { orden: number; tipo: 'Plantilla' | 'Entrevista'; nombre: string; descripcion: string | null; estado: 'Pendiente' | 'Completada' }[]
  entrevista: { estado: EstadoDeEntrevista; version: number; responder_disponible: boolean; cuestionario_version: number }
}

export interface MiEntrevista {
  estado: EstadoDeEntrevista
  version: number
  cerrada: boolean
  presentada_en: string | null
  cuestionario: Cuestionario
  respuestas: RespuestasDeEntrevista
}

export interface AgregadosDeEntrevistas {
  oculto: boolean
  minimo: number
  entrevistas: number | null
  dimensiones: { dimension: string; respondieron: number; promedio: number | null; distribucion: Record<string, number> | null }[]
  categorias: { categoria: string; menciones: number | null }[]
}

export type AccionDeSalida = 'cancelacion' | 'aplicacion' | 'cierre' | 'entrevista/no-realizada'

export interface CuerpoDeAccion {
  version: number
  razon?: string
  motivo?: MotivoDeNoRealizada
}

function limpios(filtros: FiltrosDeSalidas): Record<string, string | number> {
  const params: Record<string, string | number> = { per_page: POR_PAGINA }
  for (const [clave, valor] of Object.entries(filtros)) {
    if (valor !== undefined && valor !== '' && valor !== FILTRO_TODOS) params[clave] = valor
  }
  return params
}

const encabezado = (clave: string) => ({ headers: { 'Idempotency-Key': clave } })

// ------------------------------------------------------------------ Administrador

export async function listarSalidas(filtros: FiltrosDeSalidas, signal?: AbortSignal): Promise<PaginaDeSalidas> {
  const response = await http.get<PaginaDeSalidas>('/api/offboarding', { params: limpios(filtros), signal })
  return response.data
}

export async function obtenerResumen(signal?: AbortSignal): Promise<ResumenDeSalidas> {
  const response = await http.get<{ data: ResumenDeSalidas }>('/api/offboarding/resumen', { signal })
  return response.data.data
}

export async function obtenerSalida(id: number, signal?: AbortSignal): Promise<DetalleDeSalida> {
  const response = await http.get<{ data: DetalleDeSalida }>(`/api/offboarding/${id}`, { signal })
  return response.data.data
}

export async function obtenerContextoDeSalida(colaboradorId: number, signal?: AbortSignal): Promise<ContextoDeSalida> {
  const response = await http.get<{ data: ContextoDeSalida }>('/api/offboarding/contexto', { params: { colaborador_id: colaboradorId }, signal })
  return response.data.data
}

export async function listarRespaldos(colaboradorId: number, signal?: AbortSignal): Promise<Respaldo[]> {
  const response = await http.get<{ data: Respaldo[] }>('/api/offboarding/documentos', { params: { colaborador_id: colaboradorId }, signal })
  return response.data.data
}

/** Búsqueda de personas para el formulario: usa el listado de fichas del Administrador. */
export async function buscarColaboradores(q: string, signal?: AbortSignal): Promise<ColaboradorOpcion[]> {
  const response = await http.get<{ data: { id: number; codigo: string; nombres: string; apellidos: string }[] }>('/api/colaboradores', {
    params: { per_page: 20, ...(q ? { q } : {}) },
    signal,
  })
  return response.data.data.map((c) => ({ id: c.id, codigo: c.codigo, nombre: `${c.nombres} ${c.apellidos}` }))
}

export async function formalizarSalida(datos: DatosDeSalida, clave: string): Promise<DetalleDeSalida> {
  const response = await http.post<{ data: DetalleDeSalida }>('/api/offboarding', datos, encabezado(clave))
  return response.data.data
}

export async function ejecutarAccion(id: number, accion: AccionDeSalida, cuerpo: CuerpoDeAccion, clave: string): Promise<DetalleDeSalida> {
  const response = await http.post<{ data: DetalleDeSalida }>(`/api/offboarding/${id}/${accion}`, cuerpo, encabezado(clave))
  return response.data.data
}

export async function completarTarea(id: number, tareaId: number, cuerpo: { version: number; documento_ids: number[] }, clave: string): Promise<DetalleDeSalida> {
  const response = await http.post<{ data: DetalleDeSalida }>(`/api/offboarding/${id}/tareas/${tareaId}/completar`, cuerpo, encabezado(clave))
  return response.data.data
}

export async function consultarRespuestas(id: number, signal?: AbortSignal): Promise<RespuestasConsultadas> {
  const response = await http.get<{ data: RespuestasConsultadas }>(`/api/offboarding/${id}/entrevista/respuestas`, { signal })
  return response.data.data
}

export async function obtenerAgregados(signal?: AbortSignal): Promise<AgregadosDeEntrevistas> {
  const response = await http.get<{ data: AgregadosDeEntrevistas }>('/api/offboarding/agregados', { signal })
  return response.data.data
}

// ------------------------------------------------------------------ Colaborador

export async function obtenerMiDesvinculacion(signal?: AbortSignal): Promise<MiDesvinculacion> {
  const response = await http.get<{ data: MiDesvinculacion }>('/api/mi-desvinculacion', { signal })
  return response.data.data
}

export async function obtenerMiEntrevista(signal?: AbortSignal): Promise<MiEntrevista> {
  const response = await http.get<{ data: MiEntrevista }>('/api/mi-desvinculacion/entrevista', { signal })
  return response.data.data
}

export async function guardarMiEntrevista(cuerpo: { version: number } & RespuestasDeEntrevista, clave: string): Promise<MiEntrevista> {
  const response = await http.put<{ data: MiEntrevista }>('/api/mi-desvinculacion/entrevista', cuerpo, encabezado(clave))
  return response.data.data
}

export async function resolverMiEntrevista(accion: 'confirmacion' | 'declinacion', version: number, clave: string): Promise<MiEntrevista> {
  const response = await http.post<{ data: MiEntrevista }>(`/api/mi-desvinculacion/entrevista/${accion}`, { version }, encabezado(clave))
  return response.data.data
}
