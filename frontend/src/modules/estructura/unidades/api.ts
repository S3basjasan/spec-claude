import { bodyOf, http } from '../../../shared/api/httpClient'

export type EstadoUnidad = 'Activa' | 'Inactiva'
/** Filtro de estado del listado (solo lo usa el Administrador); `Activa` es el valor por defecto. */
export type FiltroEstado = EstadoUnidad | 'Todas'

/** Máximo de caracteres del nombre de una unidad y del de un tipo. */
export const MAX_NOMBRE = 150
export const MAX_NOMBRE_TIPO = 50

/** Tipo de la cadena: `nivel` 1 es el tipo raíz. */
export interface TipoDeUnidad {
  id: number
  nombre: string
  nivel: number
}

export interface TipoUnidad extends TipoDeUnidad {
  es_raiz: boolean
  cantidad_unidades: number
}

/** La cadena por nivel y su token de edición simultánea (`version`). */
export interface CadenaDeTipos {
  tipos: TipoUnidad[]
  version: string
}

/** Unidad padre tal como la informa el servidor: siempre sus datos actuales (`null` en la raíz). */
export interface UnidadPadre {
  id: number
  codigo: string
  nombre: string
  estado: EstadoUnidad | null
}

/** Unidad tal como aparece en el listado: en orden jerárquico y con su nivel real en el árbol (0 en la raíz). */
export interface UnidadResumen {
  id: number
  codigo: string
  nombre: string
  tipo: TipoDeUnidad
  unidad_padre: UnidadPadre | null
  nivel_arbol: number
  estado: EstadoUnidad
  tiene_dependientes: boolean
  dependientes_activas: number
  posiciones_vigentes: number
  admite_hijas: boolean
  sigue_cadena: boolean
}

export interface DependienteDeUnidad {
  id: number
  codigo: string
  nombre: string
  estado: EstadoUnidad
}

/** Persona que creó o modificó la unidad; `nombre` es `null` si el usuario ya no existe. */
export interface UsuarioDeAuditoria {
  id: number
  nombre: string | null
}

/** Solo la recibe el Administrador. */
export interface AuditoriaDeUnidad {
  created_by: UsuarioDeAuditoria | null
  created_at: string | null
  updated_by: UsuarioDeAuditoria | null
  updated_at: string | null
}

export interface UnidadDetalle extends UnidadResumen {
  version: number
  /** Fecha `YYYY-MM-DD`. */
  fecha_creacion: string
  /** Fecha `YYYY-MM-DD`; `null` mientras la unidad esté activa. */
  fecha_supresion: string | null
  dependientes: DependienteDeUnidad[]
  auditoria?: AuditoriaDeUnidad
}

export interface UnidadOpcion {
  id: number
  codigo: string
  nombre: string
  tipo: TipoDeUnidad
  nivel_arbol: number
  estado: EstadoUnidad
  admite_hijas: boolean
  /** Activa y que admite subunidades. */
  habilitada: boolean
}

/** Datos de los selectores de los modales (solo Administrador). */
export interface OpcionesDeUnidad {
  tipos: TipoDeUnidad[]
  unidades: UnidadOpcion[]
}

export interface ImpactoDeDesactivacion {
  procede: boolean
  dependientes_activas: { id: number; codigo: string; nombre: string; tipo: string }[]
  unidades_con_posiciones: { id: number; codigo: string; nombre: string; cantidad: number }[]
}

export interface ListadoDeUnidades {
  data: UnidadResumen[]
  meta: { total: number; coincidencias: number }
}

export interface FiltrosListado {
  q?: string
  /** Solo se envía para el Administrador; para el Usuario el backend siempre devuelve las activas. */
  estado?: FiltroEstado
}

/** Contenido editable de una unidad: el tipo lo calcula el servidor. */
export interface DatosUnidad {
  nombre: string
  unidad_padre_id: number | null
}

/** Un tipo en el borrador de la cadena; su nivel es su posición en la lista. */
export interface BorradorDeTipo {
  id: number
  nombre: string
}

/** Unidad que quedaría bajo una unidad padre de nivel igual o más profundo, con los tipos y niveles nuevos. */
export interface UnidadEnConflicto {
  id: number
  codigo: string
  nombre: string
  tipo: { nombre: string; nivel: number }
  unidad_padre: { id: number; codigo: string; nombre: string; tipo: { nombre: string; nivel: number } }
}

export async function listarUnidades(filtros: FiltrosListado): Promise<ListadoDeUnidades> {
  const params: Record<string, string> = {}
  if (filtros.q) params.q = filtros.q
  if (filtros.estado) params.estado = filtros.estado
  const response = await http.get<ListadoDeUnidades>('/api/unidades', { params })
  return response.data
}

export async function obtenerUnidad(id: number): Promise<UnidadDetalle> {
  const response = await http.get<{ data: UnidadDetalle }>(`/api/unidades/${id}`)
  return response.data.data
}

/** `reubicar` es la unidad que se edita: solo ofrece las padres válidas para ella. */
export async function obtenerOpciones(reubicar?: number): Promise<OpcionesDeUnidad> {
  const params = reubicar === undefined ? {} : { reubicar }
  const response = await http.get<{ data: OpcionesDeUnidad }>('/api/unidades/opciones', { params })
  return response.data.data
}

export async function obtenerImpacto(id: number): Promise<ImpactoDeDesactivacion> {
  const response = await http.get<{ data: ImpactoDeDesactivacion }>(`/api/unidades/${id}/impacto-desactivacion`)
  return response.data.data
}

export async function crearUnidad(datos: DatosUnidad): Promise<UnidadDetalle> {
  const response = await http.post<{ data: UnidadDetalle }>('/api/unidades', datos)
  return response.data.data
}

export async function actualizarUnidad(id: number, datos: DatosUnidad, version: number): Promise<UnidadDetalle> {
  const response = await http.put<{ data: UnidadDetalle }>(`/api/unidades/${id}`, { ...datos, version })
  return response.data.data
}

export async function cambiarEstadoUnidad(id: number, estado: EstadoUnidad): Promise<UnidadDetalle> {
  const response = await http.patch<{ data: UnidadDetalle }>(`/api/unidades/${id}/estado`, { estado })
  return response.data.data
}

export async function listarTipos(): Promise<CadenaDeTipos> {
  const response = await http.get<{ data: TipoUnidad[]; meta: { version: string } }>('/api/tipos-unidad')
  return { tipos: response.data.data, version: response.data.meta.version }
}

export async function crearTipo(nombre: string): Promise<TipoUnidad> {
  const response = await http.post<{ data: TipoUnidad }>('/api/tipos-unidad', { nombre })
  return response.data.data
}

export async function guardarCadena(version: string, tipos: BorradorDeTipo[]): Promise<CadenaDeTipos> {
  const response = await http.put<{ data: TipoUnidad[]; meta: { version: string } }>('/api/tipos-unidad', { version, tipos })
  return { tipos: response.data.data, version: response.data.meta.version }
}

/** Unidades en conflicto de una respuesta 422 de `guardarCadena` (vacío si el error no las trae). */
export function unidadesEnConflictoDe(error: unknown): UnidadEnConflicto[] {
  const cuerpo = bodyOf(error)
  if (typeof cuerpo === 'object' && cuerpo !== null && 'unidades_invalidas' in cuerpo && Array.isArray(cuerpo.unidades_invalidas)) {
    return cuerpo.unidades_invalidas as UnidadEnConflicto[]
  }
  return []
}
