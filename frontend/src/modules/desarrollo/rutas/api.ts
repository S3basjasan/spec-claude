import { http } from '../../../shared/api/httpClient'

export type TipoRuta = 'Especialista' | 'Gerencial' | 'Interareas' | 'JovenesProfesionales'
export type EstadoRuta = 'Borrador' | 'Vigente' | 'Archivada'
export type EstadoInscripcion = 'Activa' | 'Finalizada' | 'Retirada'
export type Grado = 'A' | 'B' | 'C' | 'D'
export type PestanaRuta = 'escalera' | 'participantes' | 'historial'
export type TipoDiferencial = 'nuevo' | 'sube' | 'baja' | 'igual'
export type ClavePermiso =
  | 'desarrollo.rutas.ver'
  | 'desarrollo.rutas.gestionar'
  | 'desarrollo.rutas.inscribir'
  | 'desarrollo.rutas.inscripciones'
  | 'desarrollo.rutas.historial'
  | 'desarrollo.rutas.ver_propio'

export const TIPOS_RUTA: TipoRuta[] = ['Especialista', 'Gerencial', 'Interareas', 'JovenesProfesionales']
export const ESTADOS_RUTA: EstadoRuta[] = ['Borrador', 'Vigente', 'Archivada']
export const ESTADOS_INSCRIPCION: EstadoInscripcion[] = ['Activa', 'Finalizada', 'Retirada']
export const MAX_NOMBRE_RUTA = 80
export const MAX_DESCRIPCION_RUTA = 500
export const MAX_NOMBRE_NIVEL = 80
export const MAX_EXPERIENCIA = 60
export const MAX_MOTIVO = 500

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

/** Fila de la lista de rutas. */
export interface RutaResumen {
  id: number
  codigo: string
  nombre: string
  descripcion: string | null
  tipo: TipoRuta
  estado: EstadoRuta
  departamento_id: number | null
  departamento: string | null
  niveles_count: number
  inscritos_count: number
}

export interface Diferencial {
  tipo: TipoDiferencial
  antes?: Grado
  delta?: number
}

export interface CompetenciaDeNivel {
  competencia_id: number
  codigo: string
  nombre: string
  tipo: string
  grado: Grado
  diferencial: Diferencial | null
}

export interface CompetenciaQuitada {
  competencia_id: number
  codigo: string
  nombre: string
  grado_anterior: Grado
}

/** Nivel de una ruta con lo que exige su puesto. Los conocimientos son solo informativos: sin grado ni diferencial. */
export interface Nivel {
  id: number
  orden: number
  puesto_id: number
  puesto: { codigo: string | null; nombre: string | null; activo: boolean | null }
  nombre: string
  experiencia_minima_anios: number | null
  competencias: CompetenciaDeNivel[]
  competencias_quitadas: CompetenciaQuitada[]
  conocimientos: { informativo: true; formacion: string[]; capacitacion: string[] }
  experiencia: { anios_minimos: number | null; diferencial_anios: number | null; requisitos: string[] }
  /** Solo en el detalle del administrador. */
  en_uso?: boolean
  inscritos_activos?: number
}

export type FuenteNoDisponible = 'estructura' | 'personal'

export interface RutaDetalle {
  id: number
  codigo: string
  nombre: string
  tipo: TipoRuta
  descripcion: string | null
  estado: EstadoRuta
  departamento_id: number | null
  departamento: string | null
  version: number
  creada_por: string | null
  creada_at: string | null
  modificada_por: string | null
  modificada_at: string | null
  niveles: Nivel[]
  inscritos_count: number
  sin_nivel: number
  fuentes_no_disponibles: FuenteNoDisponible[]
}

export interface OpcionesRutas {
  tipos: { valor: TipoRuta; nombre: string; admite_departamento: boolean }[]
  departamentos: Referencia[]
  puestos: { id: number; codigo: string; nombre: string }[]
  fuentes_no_disponibles: FuenteNoDisponible[]
}

export interface NivelEnviado {
  id?: number
  orden: number
  puesto_id: number
  nombre: string
  experiencia_minima_anios: number | null
}

export interface DatosRuta {
  nombre: string
  tipo: TipoRuta
  descripcion: string | null
  departamento_id: number | null
  niveles?: NivelEnviado[]
}

export interface CuerpoActualizarRuta extends DatosRuta {
  version: number
  niveles: NivelEnviado[]
}

export interface NivelDeInscripcion {
  id: number
  orden: number
  nombre: string
}

export interface Inscripcion {
  id: number
  ruta_id: number
  ruta: { id: number; codigo: string; nombre: string; estado: EstadoRuta } | null
  colaborador: { id: number; nombre: string; departamento: string | null }
  nivel_actual: NivelDeInscripcion | null
  nivel_objetivo: NivelDeInscripcion | null
  estado: EstadoInscripcion
  motivo: string | null
  created_at: string | null
  resuelta_at: string | null
}

export interface ColaboradorInscribible {
  id: number
  codigo: string
  nombre: string
}

export interface EventoDeHistorial {
  id: number
  tipo: 'Ruta' | 'Nivel' | 'Inscripcion'
  accion: string
  inscripcion_id: number | null
  colaborador: string | null
  valor_anterior: Record<string, unknown> | null
  valor_nuevo: Record<string, unknown> | null
  motivo: string | null
  usuario: string
  created_at: string | null
}

export interface InscripcionPropia {
  inscripcion_id: number
  ruta: { id: number; codigo: string; nombre: string; tipo: TipoRuta }
  niveles: Nivel[]
  nivel_actual_id: number | null
  nivel_objetivo_id: number | null
  siguiente_nivel_id: number | null
  puede_ver_adecuacion: boolean
}

export interface RutaDisponible {
  id: number
  codigo: string
  nombre: string
  tipo: TipoRuta
  niveles_count: number
}

export interface MiRuta {
  inscripciones: InscripcionPropia[]
  rutas_disponibles: RutaDisponible[]
  aviso: string
  fuentes_no_disponibles: FuenteNoDisponible[]
}

export interface FiltrosRutas {
  q?: string
  tipo?: TipoRuta
  estado?: EstadoRuta
  departamento_id?: number
  page?: number
  per_page?: number
}

export interface FiltrosInscripciones {
  ruta_id?: number
  estado?: EstadoInscripcion
  page?: number
  per_page?: number
}

const RUTAS = '/api/rutas'
const INSCRIPCIONES = '/api/inscripciones-ruta'

function limpiar(filtros: object): Record<string, string | number> {
  const params: Record<string, string | number> = {}
  for (const [clave, valor] of Object.entries(filtros)) {
    if (valor !== undefined && valor !== '') params[clave] = valor as string | number
  }
  return params
}

export async function obtenerPermisos(): Promise<ClavePermiso[]> {
  const { data } = await http.get<{ data: { permisos: ClavePermiso[] } }>(`${RUTAS}/permisos`)
  return data.data.permisos
}

export async function listarRutas(filtros: FiltrosRutas): Promise<Pagina<RutaResumen>> {
  const { data } = await http.get<Pagina<RutaResumen>>(RUTAS, { params: limpiar(filtros) })
  return data
}

export async function obtenerRuta(id: number): Promise<RutaDetalle> {
  const { data } = await http.get<{ data: RutaDetalle }>(`${RUTAS}/${id}`)
  return data.data
}

export async function obtenerOpcionesRutas(): Promise<OpcionesRutas> {
  const { data } = await http.get<{ data: OpcionesRutas }>(`${RUTAS}/opciones`)
  return data.data
}

export async function crearRuta(datos: DatosRuta): Promise<RutaDetalle> {
  const { data } = await http.post<{ data: RutaDetalle }>(RUTAS, datos)
  return data.data
}

export async function actualizarRuta(id: number, cuerpo: CuerpoActualizarRuta): Promise<RutaDetalle> {
  const { data } = await http.put<{ data: RutaDetalle }>(`${RUTAS}/${id}`, cuerpo)
  return data.data
}

export async function cambiarEstadoRuta(id: number, version: number, estado: Exclude<EstadoRuta, 'Borrador'>, confirmarInscritos = false): Promise<RutaDetalle> {
  const { data } = await http.patch<{ data: RutaDetalle }>(`${RUTAS}/${id}/estado`, { version, estado, confirmar_inscritos: confirmarInscritos })
  return data.data
}

export async function listarHistorial(id: number, page: number): Promise<Pagina<EventoDeHistorial>> {
  const { data } = await http.get<Pagina<EventoDeHistorial>>(`${RUTAS}/${id}/historial`, { params: { page, per_page: 10 } })
  return data
}

export async function listarInscripciones(filtros: FiltrosInscripciones): Promise<Pagina<Inscripcion>> {
  const { data } = await http.get<Pagina<Inscripcion>>(INSCRIPCIONES, { params: limpiar(filtros) })
  return data
}

export async function buscarColaboradores(q: string): Promise<ColaboradorInscribible[]> {
  const { data } = await http.get<Pagina<ColaboradorInscribible>>(`${INSCRIPCIONES}/colaboradores`, { params: limpiar({ q, per_page: 30 }) })
  return data.data
}

export interface DatosInscripcion {
  ruta_id: number
  colaborador_id: number
  nivel_actual_id: number | null
  nivel_objetivo_id: number | null
}

export async function inscribir(datos: DatosInscripcion): Promise<Inscripcion> {
  const { data } = await http.post<{ data: Inscripcion }>(INSCRIPCIONES, datos)
  return data.data
}

export async function cambiarNiveles(id: number, nivelActualId: number | null, nivelObjetivoId: number | null): Promise<Inscripcion> {
  const { data } = await http.patch<{ data: Inscripcion }>(`${INSCRIPCIONES}/${id}/niveles`, { nivel_actual_id: nivelActualId, nivel_objetivo_id: nivelObjetivoId })
  return data.data
}

export async function alcanzarNivel(id: number): Promise<{ inscripcion: Inscripcion; nota: string }> {
  const { data } = await http.patch<{ data: Inscripcion; nota: string }>(`${INSCRIPCIONES}/${id}/alcanzar`)
  return { inscripcion: data.data, nota: data.nota }
}

export async function retirarInscripcion(id: number, motivo: string): Promise<Inscripcion> {
  const { data } = await http.patch<{ data: Inscripcion }>(`${INSCRIPCIONES}/${id}/retirar`, { motivo })
  return data.data
}

export async function obtenerMiRuta(): Promise<MiRuta> {
  const { data } = await http.get<{ data: MiRuta }>(`${RUTAS}/mi-ruta`)
  return data.data
}
