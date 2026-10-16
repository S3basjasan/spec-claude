import { http } from '../../../shared/api/httpClient'

/** Valores tal como los guarda el servidor (sin tildes); la interfaz muestra «Cédula». */
export type TipoIdentificacion = 'Cedula' | 'Pasaporte' | 'Otro'

/** Estado laboral que expone el listado; hoy solo existe `SinVinculoVigente` (GP-02 añadirá otros). */
export type EstadoLaboral = string
export const ESTADO_SIN_VINCULO = 'SinVinculoVigente'
export const FILTRO_TODOS = 'Todos'

export const POR_PAGINA = 20

/** Fila del listado. `puesto` y `unidad` en null significan «No registrado»; `laboral_disponible` false, fuente caída. */
export interface ColaboradorResumen {
  id: number
  codigo: string
  nombres: string
  apellidos: string
  estado: EstadoLaboral
  puesto: string | null
  unidad: string | null
  laboral_disponible: boolean
}

export interface PaginaColaboradores {
  data: ColaboradorResumen[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
    from: number | null
    to: number | null
  }
}

export interface OpcionesDeFiltros {
  estados: { valor: string }[]
  unidades: { id: number; nombre: string }[]
  unidades_disponibles: boolean
}

export interface ProximoIngreso {
  fecha_ingreso: string
  tipo_contrato: { clave: string; etiqueta: string }
  posicion: { id: number | null; puesto: string | null; unidad: string | null; jefatura: string | null } | null
  programado: boolean
}

export interface BloqueLaboral {
  disponibilidad: 'disponible' | 'no_disponible'
  estado: EstadoLaboral | null
  puesto: string | null
  unidad: string | null
  /** Ingreso programado (GP-02); ausente o null si no hay. */
  proximo_ingreso?: ProximoIngreso | null
}

export interface HechoLaboral {
  fecha_efectiva: string
  tipo: string
  programado: boolean
  detalle: string | null
}

export interface BloqueTrayectoria {
  disponibilidad: 'disponible' | 'no_disponible'
  hechos: HechoLaboral[]
}

/** Campos editables de una ficha. */
export interface DatosFicha {
  tipo_identificacion: TipoIdentificacion
  numero_identificacion: string
  nombres: string
  apellidos: string
  fecha_nacimiento: string | null
  nacionalidad: string | null
  genero: string | null
  estado_civil: string | null
  numero_hijos: number | null
  direccion: string | null
  telefono: string | null
  correo_contacto: string | null
  emergencia_nombre: string | null
  emergencia_relacion: string | null
  emergencia_telefono: string | null
}

export interface ColaboradorDetalle extends DatosFicha {
  id: number
  codigo: string
  version: number
  created_at: string
  updated_at: string
  laboral: BloqueLaboral
  trayectoria: BloqueTrayectoria
  capacidades: { desarrollo: boolean; documentos: boolean; registrar_ingreso: boolean; exportar: boolean }
  acciones: { editar: boolean }
}

export interface FiltrosListado {
  q?: string
  estado?: string
  unidad_id?: number
  page?: number
}

export async function listarColaboradores(filtros: FiltrosListado, signal?: AbortSignal): Promise<PaginaColaboradores> {
  const params: Record<string, string | number> = { per_page: POR_PAGINA }
  if (filtros.q) params.q = filtros.q
  if (filtros.estado && filtros.estado !== FILTRO_TODOS) params.estado = filtros.estado
  if (filtros.unidad_id) params.unidad_id = filtros.unidad_id
  if (filtros.page) params.page = filtros.page
  const response = await http.get<PaginaColaboradores>('/api/colaboradores', { params, signal })
  return response.data
}

export async function obtenerFiltros(): Promise<OpcionesDeFiltros> {
  const response = await http.get<{ data: OpcionesDeFiltros }>('/api/colaboradores/filtros')
  return response.data.data
}

export async function obtenerColaborador(id: number): Promise<ColaboradorDetalle> {
  const response = await http.get<{ data: ColaboradorDetalle }>(`/api/colaboradores/${id}`)
  return response.data.data
}

export async function obtenerMiFicha(): Promise<ColaboradorDetalle> {
  const response = await http.get<{ data: ColaboradorDetalle }>('/api/mi-ficha')
  return response.data.data
}

export async function crearColaborador(datos: DatosFicha, claveDeOperacion: string): Promise<ColaboradorDetalle> {
  const response = await http.post<{ data: ColaboradorDetalle }>('/api/colaboradores', datos, {
    headers: { 'Idempotency-Key': claveDeOperacion },
  })
  return response.data.data
}

export async function actualizarColaborador(id: number, cambios: Partial<DatosFicha>, version: number): Promise<ColaboradorDetalle> {
  const response = await http.patch<{ data: ColaboradorDetalle }>(`/api/colaboradores/${id}`, { ...cambios, version })
  return response.data.data
}

// ---------------------------------------------------------------- Ingresos y reingresos (GP-02)

export interface TipoContrato {
  clave: string
  etiqueta: string
}

/** Posición elegible; la jefatura es null hasta que Estructura la publique. */
export interface PosicionElegible {
  id: number
  puesto: string
  unidad: string | null
  jefatura: string | null
}

export interface ConflictoDeFicha {
  codigo: string
  desde: string
  hasta: string | null
}

export interface OpcionesDeIngreso {
  tipos_contrato: TipoContrato[]
  ingreso_programado: boolean
  conflicto_ficha: ConflictoDeFicha | null
  posiciones: PosicionElegible[]
  total: number
}

export interface DatosIngreso {
  fecha_ingreso: string
  tipo_contrato: string
  posicion_id: number
}

export interface ResultadoIngreso {
  vinculo: {
    id: number
    fecha_ingreso: string
    tipo_contrato: TipoContrato
    registrado_en: string
    programado: boolean
  }
  asignacion: { id: number; posicion_id: number; fecha_inicio: string } | null
  situacion: { estado: string }
}

export const POR_PAGINA_POSICIONES = 50

export async function obtenerOpcionesDeIngreso(
  colaboradorId: number,
  fecha: string,
  q: string,
  signal?: AbortSignal,
): Promise<OpcionesDeIngreso> {
  const params: Record<string, string | number> = { fecha, per_page: POR_PAGINA_POSICIONES }
  if (q) params.q = q
  const response = await http.get<{
    data: Omit<OpcionesDeIngreso, 'total'>
    meta: { total: number }
  }>(`/api/colaboradores/${colaboradorId}/opciones-ingreso`, { params, signal })
  return { ...response.data.data, total: response.data.meta.total }
}

export async function registrarIngreso(
  colaboradorId: number,
  datos: DatosIngreso,
  claveDeOperacion: string,
): Promise<ResultadoIngreso> {
  const response = await http.post<{ data: ResultadoIngreso }>(`/api/colaboradores/${colaboradorId}/ingresos`, datos, {
    headers: { 'Idempotency-Key': claveDeOperacion },
  })
  return response.data.data
}
