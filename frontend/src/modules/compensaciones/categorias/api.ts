import { http } from '../../../shared/api/httpClient'

export type EstadoCategoria = 'Activo' | 'Inactivo'
export type FiltroEstadoCategoria = EstadoCategoria | 'Todos'

/** Registros por página: fijo en el servidor. */
export const POR_PAGINA = 15
export const ESTADO_INICIAL: FiltroEstadoCategoria = 'Activo'
export const LIMITE_BUSQUEDA = 100

export interface CompetenciaDeCategoria {
  id: number
  codigo: string
  nombre: string
  estado: 'Activo' | 'Inactivo'
}

export interface Indicador {
  orden: number
  texto: string
}

/** Unidad a la que aplica una categoría; `activa: false` si la unidad ya no está activa. */
export interface UnidadDeCategoria {
  id: number
  nombre: string
  activa: boolean
}

export interface UnidadDisponible {
  id: number
  codigo: string
  nombre: string
}

export interface Categoria {
  id: number
  codigo: string
  nombre: string
  descripcion: string
  competencia: CompetenciaDeCategoria | null
  competencia_activa: boolean
  seleccionable: boolean
  puntuacion_base: number
  icono: string
  estado: EstadoCategoria
  /** Solo en el detalle; el listado no los incluye. */
  indicadores?: Indicador[]
  /** Versión leída (ISO 8601 con microsegundos): se devuelve tal cual al editar o cambiar el estado. */
  updated_at: string
  /** Solo la reciben el Gestor y el líder; el colaborador ve únicamente las de su unidad y no recibe unidades. */
  unidades?: UnidadDeCategoria[]
  aplica_a_todas?: boolean
  /** Solo el Gestor: ninguna unidad asignada está activa (o no tiene unidades). */
  sin_unidades_activas?: boolean
  /** Auditoría: solo la recibe el Administrador. */
  created_by?: number | null
  updated_by?: number | null
  created_at?: string
}

export interface PaginaCategorias {
  data: Categoria[]
  meta: {
    page: number
    per_page: number
    total: number
    last_page: number
    from: number | null
    to: number | null
  }
}

export interface FiltrosCategorias {
  q?: string
  estado?: FiltroEstadoCategoria
  competencia?: number
  page?: number
}

export interface OpcionDeCompetencia {
  id: number
  codigo: string
  nombre: string
  tipo: string
  /** Solo en las opciones del filtro; el formulario recibe únicamente competencias activas. */
  estado?: 'Activo' | 'Inactivo'
}

/** Datos que el Administrador envía al crear o editar. */
export interface DatosCategoria {
  nombre: string
  descripcion: string
  competencia_id: number
  puntuacion_base: number
  icono: string
  indicadores: string[]
  /** Ids de las unidades elegidas; se omite cuando se marca «Todas las unidades». */
  unidades_ids?: number[]
  /** El servidor resuelve a las unidades activas del momento de guardar. */
  todas_las_unidades?: boolean
}

const BASE = '/api/compensaciones'

interface RespuestaListado {
  data: Categoria[]
  meta: { page: number; per_page: number; total: number; last_page: number }
}

export async function listarCategorias(filtros: FiltrosCategorias, signal?: AbortSignal): Promise<PaginaCategorias> {
  const params: Record<string, string | number> = { estado: filtros.estado ?? ESTADO_INICIAL }
  if (filtros.q) params.q = filtros.q
  if (filtros.competencia) params.competencia_id = filtros.competencia
  if (filtros.page) params.page = filtros.page

  const { data } = await http.get<RespuestaListado>(`${BASE}/categorias`, { params, signal })
  const { page, per_page, total } = data.meta

  return {
    data: data.data,
    meta: {
      ...data.meta,
      from: total === 0 ? null : (page - 1) * per_page + 1,
      to: total === 0 ? null : (page - 1) * per_page + data.data.length,
    },
  }
}

export async function obtenerCategoria(id: number, signal?: AbortSignal): Promise<Categoria> {
  const response = await http.get<{ data: Categoria }>(`${BASE}/categorias/${id}`, { signal })
  return response.data.data
}

export async function crearCategoria(datos: DatosCategoria): Promise<Categoria> {
  const response = await http.post<{ data: Categoria }>(`${BASE}/categorias`, datos)
  return response.data.data
}

export async function editarCategoria(id: number, datos: DatosCategoria, versionLeida: string): Promise<Categoria> {
  const response = await http.put<{ data: Categoria }>(`${BASE}/categorias/${id}`, { ...datos, updated_at: versionLeida })
  return response.data.data
}

export async function cambiarEstadoCategoria(id: number, estado: EstadoCategoria, versionLeida: string): Promise<Categoria> {
  const response = await http.patch<{ data: Categoria }>(`${BASE}/categorias/${id}/estado`, { estado, updated_at: versionLeida })
  return response.data.data
}

/** Opciones del filtro del listado: el Administrador recibe también las inactivas. */
export async function listarCompetenciasFiltro(signal?: AbortSignal): Promise<OpcionDeCompetencia[]> {
  const response = await http.get<{ data: OpcionDeCompetencia[] }>(`${BASE}/competencias-filtro`, { signal })
  return response.data.data
}

/** Competencias activas para el formulario (solo Administrador). */
export async function listarCompetenciasDisponibles(signal?: AbortSignal): Promise<OpcionDeCompetencia[]> {
  const response = await http.get<{ data: OpcionDeCompetencia[] }>(`${BASE}/competencias-disponibles`, { signal })
  return response.data.data
}

/** Unidades activas para el selector del formulario (solo Administrador). */
export async function listarUnidadesDisponibles(signal?: AbortSignal): Promise<UnidadDisponible[]> {
  const response = await http.get<{ data: UnidadDisponible[] }>(`${BASE}/unidades-disponibles`, { signal })
  return response.data.data
}

/** Límite exterior de la puntuación base; el máximo permitido real lo da el parámetro de puntos por colaborador. */
export const PUNTUACION_MAXIMA_EXTERIOR = 1000

export interface PuntuacionMaxima {
  /** Máximo permitido para la puntuación base: el menor entre los puntos vigentes y el cambio programado. */
  maximo: number
  /** Verdadero cuando el máximo es menor que los puntos vigentes por un cambio programado del mes siguiente. */
  bajaPorProgramado: boolean
}

interface RespuestaParametros {
  data: { clave: string; valor: number }[]
  meta: { puntuacion_maxima_categoria: number }
}

/** Lee el máximo permitido de la respuesta de parámetros (mismo endpoint de 01b.1); exige el permiso de parámetros. */
export async function obtenerPuntuacionMaxima(signal?: AbortSignal): Promise<PuntuacionMaxima> {
  const { data } = await http.get<RespuestaParametros>(`${BASE}/parametros`, { signal })
  const maximo = data.meta.puntuacion_maxima_categoria
  const vigente = data.data.find((parametro) => parametro.clave === 'puntos_por_colaborador')?.valor ?? maximo
  return { maximo, bajaPorProgramado: maximo < vigente }
}
