import { http } from '../../../shared/api/httpClient'

export type Preparacion = 'Lista' | 'EnDesarrollo' | 'NoLista' | 'Incompleta'
export type Dimension = 'Competencia' | 'Conocimiento' | 'Experiencia'
export type EstadoResultado = 'ConBrecha' | 'Cumple' | 'Supera' | 'SinEvaluacion'
export type TipoCambio = 'Promocion' | 'PuestoDirecto' | 'MismoPuesto'
export type Grado = 'A' | 'B' | 'C' | 'D'
export type ClavePermiso =
  | 'desarrollo.adecuacion.generar'
  | 'desarrollo.adecuacion.ver'
  | 'desarrollo.adecuacion.comparar'
  | 'desarrollo.adecuacion.mapa'
  | 'desarrollo.adecuacion.parametros'
  | 'desarrollo.adecuacion.ver_propio'
export type PestanaAdecuacion = 'diagnostico' | 'comparar' | 'mapa' | 'evaluaciones' | 'parametros'

export const PREPARACIONES: Preparacion[] = ['Lista', 'EnDesarrollo', 'NoLista', 'Incompleta']
export const DIMENSIONES_EN_ORDEN: Dimension[] = ['Experiencia', 'Conocimiento', 'Competencia']
export const MAX_OBSERVACIONES = 500
export const MIN_MESES_CIERRE = 1
export const MAX_MESES_CIERRE = 60

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

export interface Resultado {
  dimension: Dimension
  tipo_elemento: string | null
  elemento_id: number | null
  nombre: string | null
  requerido_actual: Grado | null
  requerido_objetivo: Grado | null
  valor: Grado | null
  requerido_actual_anios: number | null
  requerido_objetivo_anios: number | null
  valor_anios: number | null
  diferencia: number | null
  estado: EstadoResultado
  estado_etiqueta: string
  tiempo_cierre_meses: number | null
  cumplimiento?: number | null
}

/** Cumplimiento 0..1 por dimensión; nulo si la dimensión no se evaluó. */
export type CumplimientoPorDimension = Record<Dimension, number | null>

export interface Aviso {
  tipo: string
  mensaje: string
  cantidad: number
}

export interface Referencia {
  id: number
  nombre: string
}

export interface Diagnostico {
  colaborador: Referencia & { departamento: string | null }
  puesto_actual: Referencia | null
  puesto_objetivo: Referencia
  inscripcion_id: number | null
  tipo_cambio: TipoCambio
  experiencia_requerida_anios: number | null
  experiencia_de_la_persona_anios: number | null
  resultados: Resultado[]
  cumplimiento_por_dimension: CumplimientoPorDimension
  cumplimiento_por_dimension_actual: CumplimientoPorDimension
  cumplimiento_actual: number | null
  cumplimiento_objetivo: number | null
  preparacion_provisoria: Preparacion
  umbral_meses: number
  avisos: Aviso[]
  fuentes_no_disponibles: string[]
}

export interface Adecuacion {
  id: number
  codigo: string
  fecha: string | null
  generada_por: string | null
  colaborador: Referencia & { departamento: string | null }
  puesto_actual: Referencia | null
  puesto_objetivo: Referencia
  inscripcion: { id: number; ruta_nombre: string } | null
  experiencia_requerida_anios: number | null
  preparacion: Preparacion
  preparacion_etiqueta: string
  umbral_meses: number
  observaciones: string | null
  es_vigente: boolean
  resultados: Resultado[]
  cumplimiento_por_dimension: CumplimientoPorDimension
  cumplimiento_por_dimension_actual: CumplimientoPorDimension
  brechas: number
  tiempo_maximo_cierre_meses: number | null
  acciones: {
    crear_plan: { habilitado: boolean; motivo: string }
    incluir_programa: { habilitado: boolean; motivo: string }
  }
  fuentes_no_disponibles: string[]
}

export interface ColaboradorOpcion {
  id: number
  codigo: string
  nombre: string
  departamento: string | null
}

export interface NivelDeRuta {
  id: number
  orden: number
  puesto_id: number
  nombre: string
  experiencia_minima_anios?: number | null
}

export interface InscripcionOpcion {
  inscripcion_id: number
  ruta_id: number
  ruta_nombre: string
  nivel_actual: NivelDeRuta | null
  nivel_objetivo: NivelDeRuta | null
}

export interface PuestoOpcion {
  id: number
  codigo: string
  nombre: string
}

export interface HistorialDeColaboradorItem {
  id: number
  codigo: string
  fecha: string | null
  puesto_objetivo: string
  preparacion: Preparacion
  preparacion_etiqueta: string
  cumplimiento_por_dimension: CumplimientoPorDimension
}

export interface OpcionesDeColaboradores {
  colaboradores: ColaboradorOpcion[]
  puestos: PuestoOpcion[]
  departamentos: Referencia[]
  rutas: Referencia[]
  fuentes_no_disponibles: string[]
}

export interface OpcionesDeColaborador {
  inscripciones: InscripcionOpcion[]
  puestos: PuestoOpcion[]
  historial: HistorialDeColaboradorItem[]
  fuentes_no_disponibles: string[]
}

export interface ObjetivoElegido {
  colaborador_id: number
  inscripcion_id?: number
  puesto_objetivo_id?: number
  experiencia_requerida_anios?: number
}

export interface TiempoDeCierre {
  dimension: Dimension
  elemento_id: number | null
  meses: number
}

export interface DatosGenerar extends ObjetivoElegido {
  puesto_objetivo_esperado_id?: number
  tiempos_cierre: TiempoDeCierre[]
  observaciones?: string
}

export interface BrechaPropia {
  dimension: Dimension
  elemento_id: number | null
  nombre: string | null
  diferencia: number | null
  tiempo_cierre_meses: number | null
  curso: { disponible: boolean; ruta: string } | null
}

export interface AdecuacionPropia {
  id: number
  codigo: string
  fecha: string | null
  puesto_objetivo: Referencia
  preparacion: Preparacion
  preparacion_etiqueta: string
  resultados: Resultado[]
  cumplimiento_por_dimension: CumplimientoPorDimension
  cumplimiento_por_dimension_actual: CumplimientoPorDimension
  brechas: BrechaPropia[]
}

export interface MiAdecuacion {
  adecuaciones: AdecuacionPropia[]
  seleccionada: number | null
  aviso: string
  fuentes_no_disponibles: string[]
}

export interface ResumenAdecuacion {
  id: number
  codigo: string
  fecha: string
  colaborador: Referencia
  puesto_objetivo: Referencia
  preparacion: Preparacion
  preparacion_etiqueta: string
  brechas: number
  es_vigente: boolean
}

export interface FiltrosEvaluaciones {
  fecha_desde?: string
  fecha_hasta?: string
  colaborador_id?: number
  puesto_objetivo_id?: number
  preparacion?: Preparacion
  page?: number
  per_page?: number
}

const ADECUACIONES = '/api/adecuaciones'

function limpiar(filtros: object): Record<string, string | number> {
  const params: Record<string, string | number> = {}
  for (const [clave, valor] of Object.entries(filtros)) {
    if (valor !== undefined && valor !== '') params[clave] = valor as string | number
  }
  return params
}

export async function obtenerPermisos(): Promise<ClavePermiso[]> {
  const { data } = await http.get<{ data: { claves: ClavePermiso[] } }>(`${ADECUACIONES}/permisos`)
  return data.data.claves
}

export async function buscarColaboradores(q: string): Promise<OpcionesDeColaboradores> {
  const { data } = await http.get<{ data: OpcionesDeColaboradores }>(`${ADECUACIONES}/opciones`, { params: q === '' ? {} : { q } })
  return data.data
}

export async function obtenerOpcionesDeColaborador(colaboradorId: number): Promise<OpcionesDeColaborador> {
  const { data } = await http.get<{ data: OpcionesDeColaborador }>(`${ADECUACIONES}/opciones`, { params: { colaborador_id: colaboradorId } })
  return data.data
}

export async function previsualizar(datos: ObjetivoElegido): Promise<Diagnostico> {
  const { data } = await http.post<{ data: Diagnostico }>(`${ADECUACIONES}/previsualizar`, datos)
  return data.data
}

export async function generar(datos: DatosGenerar): Promise<Adecuacion> {
  const { data } = await http.post<{ data: Adecuacion }>(ADECUACIONES, datos)
  return data.data
}

/** Las adecuaciones vigentes del usuario autenticado; la identidad sale siempre de la sesión. */
export async function obtenerMiAdecuacion(puestoObjetivoId?: number): Promise<MiAdecuacion> {
  const { data } = await http.get<{ data: MiAdecuacion }>(`${ADECUACIONES}/mi-adecuacion`, {
    params: puestoObjetivoId === undefined ? {} : { puesto_objetivo_id: puestoObjetivoId },
  })
  return data.data
}

export async function listarAdecuaciones(filtros: FiltrosEvaluaciones): Promise<Pagina<ResumenAdecuacion>> {
  const { data } = await http.get<Pagina<ResumenAdecuacion>>(ADECUACIONES, { params: limpiar(filtros) })
  return data
}

export async function obtenerAdecuacion(id: number): Promise<Adecuacion> {
  const { data } = await http.get<{ data: Adecuacion }>(`${ADECUACIONES}/${id}`)
  return data.data
}

export interface PesosConsulta {
  competencias: number
  conocimientos: number
  experiencia: number
}

export interface CandidatoRanking {
  posicion: number
  colaborador: Referencia
  adecuacion_id: number
  puntaje: number
  cumplimiento_por_dimension: CumplimientoPorDimension
  preparacion: Preparacion
  preparacion_etiqueta: string
  brechas: number
  puesto_actual: string | null
}

export interface Comparacion {
  puesto_objetivo: Referencia
  pesos: Record<Dimension, number>
  ranking: CandidatoRanking[]
  incompletos: { colaborador: Referencia; adecuacion_id: number }[]
  sin_adecuacion: { colaborador: Referencia }[]
  fuentes_no_disponibles: string[]
}

export interface ConsultaComparacion {
  puesto_objetivo_id: number
  colaborador_ids: number[]
  /** Pesos solo para esta consulta; sin ellos se usan los guardados. */
  pesos?: PesosConsulta
}

export const MIN_CANDIDATOS = 2
export const MAX_CANDIDATOS = 20

/** Calcula el ranking en el momento; no se guarda nada. */
export async function comparar(consulta: ConsultaComparacion): Promise<Comparacion> {
  const params: Record<string, string | number | number[]> = {
    puesto_objetivo_id: consulta.puesto_objetivo_id,
    colaborador_ids: consulta.colaborador_ids,
  }
  if (consulta.pesos !== undefined) {
    params.peso_competencias = consulta.pesos.competencias
    params.peso_conocimientos = consulta.pesos.conocimientos
    params.peso_experiencia = consulta.pesos.experiencia
  }
  const { data } = await http.get<{ data: Comparacion }>(`${ADECUACIONES}/comparar`, {
    params,
    paramsSerializer: { indexes: true },
  })
  return data.data
}

export interface FilaDeMapa {
  adecuacion_id: number
  codigo: string
  colaborador: Referencia & { departamento: string | null }
  ruta: Referencia | null
  puesto_actual: Referencia | null
  puesto_objetivo: Referencia
  preparacion: Preparacion
  preparacion_etiqueta: string
  cumplimiento_por_dimension: CumplimientoPorDimension
  brechas: number
  tiempo_maximo_cierre_meses: number | null
}

export interface Mapa {
  data: FilaDeMapa[]
  kpis: Record<Preparacion, number>
  meta: Meta
  fuentes_no_disponibles: string[]
}

export interface FiltrosMapa {
  departamento_id?: number
  ruta_id?: number
  preparacion?: Preparacion
  page?: number
  per_page?: number
}

export async function obtenerMapa(filtros: FiltrosMapa): Promise<Mapa> {
  const { data } = await http.get<Mapa>(`${ADECUACIONES}/mapa`, { params: limpiar(filtros) })
  return data
}

export interface EntradaDeHistorialDeParametros {
  campo: 'peso_competencias' | 'peso_conocimientos' | 'peso_experiencia' | 'umbral_meses'
  valor_anterior: string
  valor_nuevo: string
  usuario: { id: number; nombre: string | null }
  fecha: string
}

export interface Parametros {
  pesos: Record<Dimension, number>
  umbral_meses: number
  historial: EntradaDeHistorialDeParametros[]
  meta: Meta
}

export interface DatosParametros {
  pesos: Record<Dimension, number>
  umbral_meses: number
}

export async function obtenerParametrosDeAdecuacion(page = 1): Promise<Parametros> {
  const { data } = await http.get<{ data: Parametros }>(`${ADECUACIONES}/parametros`, { params: { page, per_page: 10 } })
  return data.data
}

export async function actualizarParametros(datos: DatosParametros): Promise<Parametros> {
  const { data } = await http.put<{ data: Parametros }>(`${ADECUACIONES}/parametros`, datos)
  return data.data
}
