import { http } from '../../../shared/api/httpClient'

export type EstadoPosicion = 'Ocupada' | 'Vacante' | 'Suprimida'
/** Valores tal como los guarda el servidor (sin tilde); la interfaz muestra "Subrogación". */
export type Modalidad = 'Nombramiento' | 'Contrato' | 'Encargo' | 'Subrogacion'
/** Filtro de estado del listado; `Suprimida` y `Todas` solo los ofrece el Administrador. `Vigentes` es el valor por defecto. */
export type FiltroEstado = 'Vigentes' | 'Ocupada' | 'Vacante' | 'Suprimida' | 'Todas'

export const MODALIDADES: Modalidad[] = ['Nombramiento', 'Contrato', 'Encargo', 'Subrogacion']

/** Posición tal como aparece en el listado. */
export interface PosicionResumen {
  id: number
  codigo: string
  estado: EstadoPosicion
  modalidad: Modalidad
  puesto: { id: number; codigo: string; nombre: string }
  unidad: { id: number; codigo: string; nombre: string; estado: string }
  /** Nulo en la máxima autoridad. */
  supervisor: { id: number; codigo: string; nombre: string } | null
  /** Nulo si la posición no tiene ocupante. */
  ocupante: UsuarioReferido | null
  subordinadas_vigentes: number
}

export interface UsuarioReferido {
  id: number
  nombre: string | null
}

export interface AuditoriaPosicion {
  created_by: UsuarioReferido | null
  created_at: string | null
  updated_by: UsuarioReferido | null
  updated_at: string | null
}

export interface PosicionDetalle {
  id: number
  codigo: string
  estado: EstadoPosicion
  modalidad: Modalidad
  puesto: { id: number; codigo: string; nombre: string; descripcion: string }
  unidad: { id: number; codigo: string; nombre: string; estado: string; tipo: { id: number; nombre: string; nivel: number } }
  supervisor: { id: number; codigo: string; nombre: string; ocupante: UsuarioReferido | null } | null
  subordinadas_vigentes: number
  version: number
  ocupante: UsuarioReferido | null
  subordinadas: { id: number; codigo: string; puesto: string; estado: EstadoPosicion; ocupante: UsuarioReferido | null }[]
  /** Solo la recibe el Administrador. */
  auditoria?: AuditoriaPosicion
}

export interface PaginaPosiciones {
  data: PosicionResumen[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
    from: number | null
    to: number | null
  }
}

export interface FiltrosListado {
  q?: string
  estado?: FiltroEstado
  unidad_organizacional_id?: number
  page?: number
}

export interface OpcionesDePosicion {
  puestos: { id: number; codigo: string; nombre: string; descripcion: string }[]
  unidades: {
    id: number
    codigo: string
    nombre: string
    tipo: { id: number; nombre: string; nivel: number }
    nivel_arbol: number
    estado: string
    unidad_padre_id: number | null
    /** Ids de las unidades superiores, de la padre a la raíz (vacío en la raíz). */
    superiores: number[]
    /** Solo las unidades habilitadas pueden elegirse. */
    habilitada: boolean
    motivo_deshabilitada: 'inactiva' | 'raiz_ocupada' | null
  }[]
  posiciones: {
    id: number
    codigo: string
    puesto: string
    unidad_organizacional_id: number
    unidad: string
    ocupante: string | null
  }[]
  hay_maxima_autoridad: boolean
  /** Con `excluir`: unidades de las subordinadas directas vigentes; la nueva unidad debe ser igual o superior a cada una. */
  unidades_minimas: number[]
}

/** Unidad del selector del filtro del listado. */
export interface UnidadParaFiltro {
  id: number
  codigo: string
  nombre: string
  nivel_arbol: number
}

/** Datos editables de una posición; el puesto solo se envía al crear. */
export interface DatosPosicion {
  puesto_id?: number
  unidad_organizacional_id: number
  posicion_jefe_id: number | null
  modalidad: Modalidad
}

export async function listarPosiciones(filtros: FiltrosListado): Promise<PaginaPosiciones> {
  const params: Record<string, string | number> = {}
  if (filtros.q) params.q = filtros.q
  if (filtros.estado) params.estado = filtros.estado
  if (filtros.unidad_organizacional_id) params.unidad_organizacional_id = filtros.unidad_organizacional_id
  if (filtros.page) params.page = filtros.page
  const response = await http.get<PaginaPosiciones>('/api/posiciones', { params })
  return response.data
}

export async function obtenerPosicion(id: number): Promise<PosicionDetalle> {
  const response = await http.get<{ data: PosicionDetalle }>(`/api/posiciones/${id}`)
  return response.data.data
}

/** `excluir` es la posición que se edita: el servidor la deja fuera de los supervisores, con sus subordinadas. */
export async function obtenerOpciones(excluir?: number): Promise<OpcionesDePosicion> {
  const params = excluir === undefined ? undefined : { excluir }
  const response = await http.get<{ data: OpcionesDePosicion }>('/api/posiciones/opciones', { params })
  return response.data.data
}

/** Unidades en orden de árbol para el filtro del listado (lectura de `GET /api/unidades`; sin importar su módulo). */
export async function listarUnidadesParaFiltro(): Promise<UnidadParaFiltro[]> {
  const response = await http.get<{ data: UnidadParaFiltro[] }>('/api/unidades', { params: { estado: 'Todas' } })
  return response.data.data.map(({ id, codigo, nombre, nivel_arbol }) => ({ id, codigo, nombre, nivel_arbol }))
}

export async function crearPosicion(datos: DatosPosicion): Promise<PosicionDetalle> {
  const response = await http.post<{ data: PosicionDetalle }>('/api/posiciones', datos)
  return response.data.data
}

export async function actualizarPosicion(id: number, datos: DatosPosicion, version: number): Promise<PosicionDetalle> {
  const response = await http.put<{ data: PosicionDetalle }>(`/api/posiciones/${id}`, { ...datos, version })
  return response.data.data
}

export async function suprimirPosicion(id: number): Promise<PosicionDetalle> {
  const response = await http.post<{ data: PosicionDetalle }>(`/api/posiciones/${id}/suprimir`)
  return response.data.data
}
