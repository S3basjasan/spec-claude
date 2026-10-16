import { http } from '../../../shared/api/httpClient'

export type TipoCompetencia = 'Cardinal' | 'Especifica'
export type EstadoCompetencia = 'Activo' | 'Inactivo'
/** Filtro de estado de la matriz (solo lo usa el Administrador); `Activo` es el valor por defecto. */
export type FiltroEstado = EstadoCompetencia | 'Todos'
/** `con`/`sin`: con o sin cursos disponibles; `sin_oferta`: ningún curso ni micro-contenido disponible. */
export type FiltroCobertura = 'con' | 'sin' | 'sin_oferta'
export type Nivel = 'A' | 'B' | 'C' | 'D'
export const NIVELES: Nivel[] = ['A', 'B', 'C', 'D']
export type EstadoRecurso = 'Disponible' | 'Archivado'
export type TipoRecurso = 'curso' | 'microcontenido'
export type AccionRecurso = 'Iniciar' | 'Ver'

export interface AreaFormativa {
  id: number
  nombre: string
}

/** Fila del listado: los contadores cuentan solo recursos disponibles; `nivel_actual` solo llega al colaborador. */
export interface FilaCatalogo {
  competencia_id: number
  codigo: string
  nombre: string
  tipo: TipoCompetencia
  estado: EstadoCompetencia
  area_formativa: AreaFormativa | null
  cursos_count: number
  microcontenidos_count: number
  cubierta: boolean
  nivel_actual?: Nivel | null
}

/** Curso o micro-contenido; sin `url_destino` el botón se muestra deshabilitado con "Próximamente". */
export interface Recurso {
  id: number
  nombre: string
  tipo: string
  duracion_minutos: number
  formador: string | null
  estado: EstadoRecurso
  accion: AccionRecurso
  url_destino: string | null
  /** Solo en la lista de recursos disponibles: en cuántas competencias se usa. */
  competencias_count?: number
}

export interface DetalleFormativo {
  competencia: {
    id: number
    codigo: string
    nombre: string
    descripcion: string
    tipo: TipoCompetencia
    estado: EstadoCompetencia
    grados: Record<Nivel, string>
  }
  area_formativa: AreaFormativa | null
  cursos: Recurso[]
  microcontenidos: Recurso[]
  /** Solo administrador. */
  version?: number
  ultima_configuracion?: { usuario: string; fecha: string } | null
  /** Solo colaborador. */
  nivel_actual?: Nivel | null
  nivel_requerido?: Nivel | null
}

export interface Meta {
  current_page: number
  per_page: number
  total: number
  last_page: number
  from: number | null
  to: number | null
}

export interface PaginaCatalogo {
  data: FilaCatalogo[]
  meta: Meta
}

export interface FiltrosCatalogo {
  q?: string
  tipo?: TipoCompetencia
  area_formativa_id?: number
  cobertura?: FiltroCobertura
  estado?: FiltroEstado
  page?: number
  per_page?: number
}

export interface Cobertura {
  activas_totales: number
  activas_cubiertas: number
  porcentaje: number
}

export interface Recomendado {
  competencia_id: number
  nombre: string
  tipo: TipoCompetencia
  area_formativa: AreaFormativa | null
  nivel_actual: Nivel | null
  nivel_requerido: Nivel
  cursos_count: number
  microcontenidos_count: number
}

export interface EntradaHistorial {
  fecha: string
  usuario: string
  tipo_cambio: 'AreaFormativa' | 'Curso' | 'Microcontenido'
  accion: 'Asociado' | 'Quitado' | 'Cambiado' | 'Renombrado'
  recurso: string | null
  valor_anterior: string | null
  valor_nuevo: string | null
}

export interface PaginaHistorial {
  data: EntradaHistorial[]
  meta: Meta
}

/** Configuración que reemplaza a la guardada; `version` es la del detalle con el que se abrió el panel. */
export interface DatosConfiguracion {
  area_formativa_id: number | null
  curso_ids: number[]
  microcontenido_ids: number[]
}

/** Máximo de caracteres del nombre de un área formativa. */
export const MAX_NOMBRE_AREA = 60

const BASE = '/api/catalogo-formativo'

export async function listarCatalogo(filtros: FiltrosCatalogo): Promise<PaginaCatalogo> {
  const params: Record<string, string | number> = {}
  if (filtros.q) params.q = filtros.q
  if (filtros.tipo) params.tipo = filtros.tipo
  if (filtros.area_formativa_id) params.area_formativa_id = filtros.area_formativa_id
  if (filtros.cobertura) params.cobertura = filtros.cobertura
  if (filtros.estado) params.estado = filtros.estado
  if (filtros.page) params.page = filtros.page
  if (filtros.per_page) params.per_page = filtros.per_page
  const response = await http.get<PaginaCatalogo>(BASE, { params })
  return response.data
}

/** Todas las competencias visibles (recorre las páginas): las tarjetas se agrupan por área, sin paginar. */
export async function listarCatalogoCompleto(): Promise<FilaCatalogo[]> {
  const filas: FilaCatalogo[] = []
  let pagina = 1
  let ultima: number
  do {
    const respuesta = await listarCatalogo({ page: pagina, per_page: 50 })
    filas.push(...respuesta.data)
    ultima = respuesta.meta.last_page
    pagina += 1
  } while (pagina <= ultima)
  return filas
}

export async function obtenerDetalle(id: number): Promise<DetalleFormativo> {
  const response = await http.get<{ data: DetalleFormativo }>(`${BASE}/${id}`)
  return response.data.data
}

export async function obtenerCobertura(): Promise<Cobertura> {
  const response = await http.get<{ data: Cobertura }>(`${BASE}/cobertura`)
  return response.data.data
}

export async function listarAreas(): Promise<AreaFormativa[]> {
  const response = await http.get<{ data: AreaFormativa[] }>(`${BASE}/areas`)
  return response.data.data
}

export async function crearArea(nombre: string): Promise<AreaFormativa> {
  const response = await http.post<{ data: AreaFormativa }>(`${BASE}/areas`, { nombre })
  return response.data.data
}

export async function renombrarArea(id: number, nombre: string): Promise<AreaFormativa> {
  const response = await http.patch<{ data: AreaFormativa }>(`${BASE}/areas/${id}`, { nombre })
  return response.data.data
}

export async function listarRecursosDisponibles(tipo: TipoRecurso, competenciaId: number, q?: string): Promise<Recurso[]> {
  const params: Record<string, string | number> = { tipo, competencia_id: competenciaId }
  if (q) params.q = q
  const response = await http.get<{ data: Recurso[] }>(`${BASE}/recursos-disponibles`, { params })
  return response.data.data
}

export async function guardarConfiguracion(id: number, datos: DatosConfiguracion, version: number): Promise<DetalleFormativo> {
  const response = await http.put<{ data: DetalleFormativo }>(`${BASE}/${id}`, { ...datos, version })
  return response.data.data
}

export async function listarRecomendados(): Promise<Recomendado[]> {
  const response = await http.get<{ data: Recomendado[] }>(`${BASE}/recomendados`)
  return response.data.data
}

export async function listarHistorial(id: number, page: number): Promise<PaginaHistorial> {
  const response = await http.get<PaginaHistorial>(`${BASE}/${id}/historial`, { params: { page, per_page: 5 } })
  return response.data
}
