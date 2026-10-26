import { http } from '../../../shared/api/httpClient'

export type TipoActuacion = 'Ascenso' | 'Traslado' | 'Permiso' | 'Licencia' | 'Vacaciones' | 'Ausencia'
export type TipoDeNovedad = 'Permiso' | 'Licencia' | 'Vacaciones'
export type TipoDeMovimiento = 'Ascenso' | 'Traslado'

export type EstadoActuacion =
  | 'Presentada'
  | 'EnRevision'
  | 'Aprobada'
  | 'Rechazada'
  | 'Retirada'
  | 'Programada'
  | 'Aplicada'
  | 'IncidenciaDeAplicacion'
  | 'Informada'
  | 'Revisada'
  | 'Justificada'

export type GrupoActuaciones = 'pendientes' | 'resueltas' | 'programados' | 'aplicados' | 'incidencias'

export const TIPOS_ACTUACION: TipoActuacion[] = ['Ascenso', 'Traslado', 'Permiso', 'Licencia', 'Vacaciones', 'Ausencia']
export const TIPOS_DE_NOVEDAD: TipoDeNovedad[] = ['Permiso', 'Licencia', 'Vacaciones']
export const GRUPOS: GrupoActuaciones[] = ['pendientes', 'resueltas', 'programados', 'aplicados', 'incidencias']
export const FILTRO_TODOS = 'todos'
export const POR_PAGINA = 20

export interface FilaActuacion {
  id: number
  tipo: TipoActuacion
  categoria: 'Movimiento' | 'Novedad'
  estado: EstadoActuacion
  version: number
  colaborador: { id: number; codigo: string; nombre: string }
  presentada_en: string
  fecha_presentacion: string
  presentada_por: { id: number; nombre: string | null }
  en_nombre_de_colaborador: boolean
  periodo: { inicio: string; fin: string } | null
  fecha_efectiva: string | null
  aplicacion_estado: 'Pendiente' | 'Aplicada' | 'Incidencia' | null
}

export interface PaginaActuaciones {
  data: FilaActuacion[]
  meta: { current_page: number; per_page: number; total: number; last_page: number; from: number | null; to: number | null }
}

export interface ResumenActuaciones {
  pendientes: number
  en_revision: number
  programados: number
  aplicados: number
  incidencias: number
}

export interface FiltrosActuaciones {
  tipo?: string
  estado?: string
  grupo?: string
  colaborador_id?: number
  desde?: string
  hasta?: string
  page?: number
}

export type EstadoDeComprobacion = 'Cumple' | 'Pendiente' | 'NoCumple' | 'NoVerificable'

export interface Comprobacion {
  clave: 'vinculo' | 'documento' | 'origen' | 'destino' | 'evaluacion' | 'cruce'
  estado: EstadoDeComprobacion
  texto: string
}

export interface CruceDePeriodo {
  id: number
  tipo: TipoActuacion
  estado: EstadoActuacion
  inicio: string
  fin: string
}

export interface ParametrosConservados {
  disponible?: boolean
  justificacion_obligatoria?: boolean
  documento_obligatorio?: boolean
  documento_adicional_obligatorio?: boolean
  anticipacion_dias?: number
}

export interface EventoDeHistorial {
  evento: string
  estado_anterior: string | null
  estado_nuevo: string
  actor: { tipo: 'usuario' | 'sistema'; id: number | null; nombre: string | null }
  justificacion: string | null
  detalle: Record<string, unknown> | null
  en: string
}

export interface DetalleDeMovimiento {
  fecha_efectiva: string
  posicion_actual: { id: number; puesto: string; unidad: string | null } | null
  posicion_destino: { id: number; puesto: string | null; unidad: string | null }
  evaluacion: { estado: 'Valida' | 'NoDisponible' | 'NoVerificable'; motivo: string | null; contexto: Record<string, unknown> | null; consultada_en: string | null } | null
  aplicacion: {
    estado: 'Pendiente' | 'Aplicada' | 'Incidencia'
    aplicada_en: string | null
    por_sistema: boolean
    intentos: number
    incidencia: { codigo: string; detalle: string | null } | null
  }
}

export interface DetalleActuacion {
  id: number
  tipo: TipoActuacion
  categoria: 'Movimiento' | 'Novedad'
  estado: EstadoActuacion
  version: number
  colaborador: { id: number; codigo: string | null; nombre: string | null }
  presentada: { en: string; fecha: string; por: { id: number; nombre: string | null }; en_nombre_de_colaborador: boolean }
  motivo: string | null
  periodo: { inicio: string; fin: string; ocurrida: boolean } | null
  revision: { observacion: string } | null
  cruce_reconocido: { por: number; en: string | null } | null
  movimiento: DetalleDeMovimiento | null
  politica: { version_id: number; numero: number; tipo: string; parametros: ParametrosConservados; reglas_fijas: { clave: string; texto: string }[] }
  comprobaciones: Comprobacion[]
  puede_aprobar: boolean
  motivo_no_aprobable: string | null
  cruces: CruceDePeriodo[]
  documentos: { id: number; disponible: boolean }[]
  acciones: {
    revisar: boolean
    rechazar: boolean
    aprobar: boolean
    retirar: boolean
    revisar_ausencia: boolean
    aplicar: boolean
  }
  historial: EventoDeHistorial[]
}

export interface DestinoElegible {
  id: number
  puesto: string
  unidad: string
  jefatura: string | null
}

export interface ContextoDeMovimiento {
  colaborador: { id: number; codigo: string | null; nombre: string | null }
  vinculo_vigente: boolean
  posicion_actual: { id: number; puesto: string; unidad: string | null } | null
  destinos: { items: DestinoElegible[]; total: number }
  politicas: Record<'ascensos' | 'traslados', { situacion: 'SinConfigurar' | 'Vigente' | 'NoAdmiteSolicitudes' | 'NoDisponible'; numero: number | null; parametros: ParametrosConservados | null }>
}

export interface Respaldo {
  id: number
  titulo: string
}

/** Forma comunicable al Colaborador: no contiene los campos restringidos del Administrador. */
export type MiSolicitud =
  | { id: number; tipo: TipoDeMovimiento; categoria: 'Movimiento'; estado: 'Aprobada' | 'Programada' | 'Aplicada'; fecha_efectiva: string | null }
  | {
      id: number
      tipo: TipoDeNovedad | 'Ausencia'
      categoria: 'Novedad'
      estado: EstadoActuacion
      version: number
      presentada_en: string
      motivo: string | null
      periodo: { inicio: string | null; fin: string | null }
      politica: { numero: number; requisitos: ParametrosConservados }
      resultado: { sentido: string; justificacion: string | null; resuelta_en: string | null } | null
      retirable: boolean
    }

export interface DatosDeNovedad {
  tipo: TipoDeNovedad
  fecha_inicio: string
  fecha_fin: string
  motivo?: string
  documento_ids: number[]
}

export interface DatosDeAusencia {
  fecha_inicio: string
  fecha_fin: string
  motivo?: string
  documento_ids: number[]
}

export interface DatosDeMovimiento {
  tipo: TipoDeMovimiento
  posicion_destino_id: number
  fecha_efectiva: string
  motivo: string
  documento_ids: number[]
}

function limpios(filtros: FiltrosActuaciones): Record<string, string | number> {
  const params: Record<string, string | number> = { per_page: POR_PAGINA }
  for (const [clave, valor] of Object.entries(filtros)) {
    if (valor !== undefined && valor !== '' && valor !== FILTRO_TODOS) params[clave] = valor
  }
  return params
}

const encabezado = (clave: string) => ({ headers: { 'Idempotency-Key': clave } })

// ------------------------------------------------------------------ Administrador

export async function listarActuaciones(filtros: FiltrosActuaciones, signal?: AbortSignal): Promise<PaginaActuaciones> {
  const response = await http.get<PaginaActuaciones>('/api/movimientos-personal', { params: limpios(filtros), signal })
  return response.data
}

export async function obtenerResumen(signal?: AbortSignal): Promise<ResumenActuaciones> {
  const response = await http.get<{ data: ResumenActuaciones }>('/api/movimientos-personal/resumen', { signal })
  return response.data.data
}

export async function obtenerActuacion(id: number, signal?: AbortSignal): Promise<DetalleActuacion> {
  const response = await http.get<{ data: DetalleActuacion }>(`/api/movimientos-personal/${id}`, { signal })
  return response.data.data
}

export async function obtenerContextoDeMovimiento(colaboradorId: number, q: string, signal?: AbortSignal): Promise<ContextoDeMovimiento> {
  const response = await http.get<{ data: ContextoDeMovimiento }>('/api/movimientos-personal/contexto-de-movimiento', {
    params: { colaborador_id: colaboradorId, ...(q ? { q } : {}), per_page: 20 },
    signal,
  })
  return response.data.data
}

export async function listarRespaldosDeAdministrador(colaboradorId: number, signal?: AbortSignal): Promise<Respaldo[]> {
  const response = await http.get<{ data: Respaldo[] }>('/api/movimientos-personal/documentos', { params: { colaborador_id: colaboradorId }, signal })
  return response.data.data
}

export interface ColaboradorOpcion {
  id: number
  codigo: string
  nombre: string
}

/** Búsqueda de personas para los filtros y formularios: usa el listado de fichas del Administrador. */
export async function buscarColaboradores(q: string, signal?: AbortSignal): Promise<ColaboradorOpcion[]> {
  const response = await http.get<{ data: { id: number; codigo: string; nombres: string; apellidos: string }[] }>('/api/colaboradores', {
    params: { per_page: 20, ...(q ? { q } : {}) },
    signal,
  })
  return response.data.data.map((c) => ({ id: c.id, codigo: c.codigo, nombre: `${c.nombres} ${c.apellidos}` }))
}

export async function presentarNovedadComoAdministrador(colaboradorId: number, datos: DatosDeNovedad, clave: string): Promise<DetalleActuacion> {
  const response = await http.post<{ data: DetalleActuacion }>('/api/movimientos-personal/novedades', { colaborador_id: colaboradorId, ...datos }, encabezado(clave))
  return response.data.data
}

export async function informarAusencia(colaboradorId: number, datos: DatosDeAusencia, clave: string): Promise<DetalleActuacion> {
  const response = await http.post<{ data: DetalleActuacion }>('/api/movimientos-personal/ausencias', { colaborador_id: colaboradorId, ...datos }, encabezado(clave))
  return response.data.data
}

export async function proponerMovimiento(colaboradorId: number, datos: DatosDeMovimiento, clave: string): Promise<DetalleActuacion> {
  const response = await http.post<{ data: DetalleActuacion }>('/api/movimientos-personal/movimientos', { colaborador_id: colaboradorId, ...datos }, encabezado(clave))
  return response.data.data
}

export type AccionDeActuacion = 'revision' | 'aprobacion' | 'rechazo' | 'retiro' | 'revision-de-ausencia' | 'aplicacion'

export interface CuerpoDeAccion {
  version: number
  justificacion?: string
  reconoce_cruce?: boolean
  resultado?: 'Revisada' | 'Justificada'
  observacion?: string
}

export async function ejecutarAccion(id: number, accion: AccionDeActuacion, cuerpo: CuerpoDeAccion, clave: string): Promise<DetalleActuacion> {
  const response = await http.post<{ data: DetalleActuacion }>(`/api/movimientos-personal/${id}/${accion}`, cuerpo, encabezado(clave))
  return response.data.data
}

// ------------------------------------------------------------------ Colaborador

export async function listarMisSolicitudes(signal?: AbortSignal): Promise<MiSolicitud[]> {
  const response = await http.get<{ data: MiSolicitud[] }>('/api/mis-solicitudes', { signal })
  return response.data.data
}

export async function listarMisRespaldos(signal?: AbortSignal): Promise<Respaldo[]> {
  const response = await http.get<{ data: Respaldo[] }>('/api/mis-solicitudes/documentos', { signal })
  return response.data.data
}

export async function presentarMiNovedad(datos: DatosDeNovedad, clave: string): Promise<MiSolicitud> {
  const response = await http.post<{ data: MiSolicitud }>('/api/mis-solicitudes/novedades', datos, encabezado(clave))
  return response.data.data
}

export async function retirarMiNovedad(id: number, version: number, clave: string): Promise<MiSolicitud> {
  const response = await http.post<{ data: MiSolicitud }>(`/api/mis-solicitudes/${id}/retiro`, { version }, encabezado(clave))
  return response.data.data
}
