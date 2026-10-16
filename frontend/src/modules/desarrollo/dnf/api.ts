import { http } from '../../../shared/api/httpClient'

export type Alcance = 'Individual' | 'Departamento'
export type Prioridad = 'Critica' | 'Alta' | 'Media' | 'Baja'
export type EstadoBrecha = 'ConBrecha' | 'Cumple' | 'Supera' | 'SinEvaluacion'
export type Nivel = 'A' | 'B' | 'C' | 'D'
export type TipoCompetencia = 'Cardinal' | 'Especifica'

export const NIVELES: Nivel[] = ['A', 'B', 'C', 'D']
export const PRIORIDADES: Prioridad[] = ['Critica', 'Alta', 'Media', 'Baja']
export const ESTADOS: EstadoBrecha[] = ['ConBrecha', 'Cumple', 'Supera', 'SinEvaluacion']

/** Escala numérica del cálculo (D=1 … A=4); solo se usa para dibujar barras, el cálculo es del backend. */
export const VALOR_NIVEL: Record<Nivel, number> = { A: 4, B: 3, C: 2, D: 1 }

/** Mayor valor = más prioritaria (solo para ordenar en pantalla). */
export const ORDEN_PRIORIDAD: Record<Prioridad, number> = { Critica: 4, Alta: 3, Media: 2, Baja: 1 }

export interface Brecha {
  competencia_id: number
  codigo: string
  nombre: string
  tipo: TipoCompetencia
  nivel_requerido: Nivel
  nivel_actual: Nivel | null
  diferencia: number | null
  brecha_porcentaje: number | null
  prioridad: Prioridad | null
  estado: EstadoBrecha
  competencia_activa: boolean
}

export interface PuestoDnf {
  id: number
  codigo: string
  nombre: string
}

export interface ResultadoColaborador {
  colaborador_id: number
  nombre: string
  puesto: PuestoDnf | null
  cantidad_brechas: number
  prioridad_mas_alta: Prioridad | null
  sin_evaluacion: number
  /** Ausente en el resumen de una masiva sin colaborador elegido. */
  brechas?: Brecha[]
}

export interface PlanReferencia {
  id: number
  generado_por: { id: number; nombre: string } | null
  generado_en: string | null
}

export interface ResumenDnf {
  colaboradores: number
  cantidad_brechas: number
  prioridad_mas_alta: Prioridad | null
  sin_evaluacion: number
}

export interface Dnf {
  id: number
  codigo: string
  alcance: Alcance
  fecha: string
  departamento: { id: number; nombre: string } | null
  generado_por: { id: number; nombre: string } | null
  competencias_omitidas: number
  plan_formacion: PlanReferencia | null
  resumen: ResumenDnf
  colaboradores: ResultadoColaborador[]
}

export interface Excluido {
  colaborador_id: number
  nombre: string
  motivo: 'SinPuesto' | 'SinPerfil' | 'SinVinculoVigente'
}

export interface AvisosDnf {
  competencias_omitidas: number
  sin_evaluacion: number
  excluidos: Excluido[]
}

export interface DnfGenerada {
  data: Dnf
  avisos: AvisosDnf
}

export interface Meta {
  page: number
  per_page: number
  total: number
  last_page: number
}

export interface FilaHistorial {
  id: number
  codigo: string
  fecha: string
  alcance: Alcance
  colaborador: { id: number; nombre: string } | null
  departamento: { id: number; nombre: string } | null
  cantidad_brechas: number
  prioridad_mas_alta: Prioridad | null
  tiene_plan: boolean
}

export interface PaginaHistorial {
  data: FilaHistorial[]
  meta: Meta
}

export interface FiltrosHistorial {
  fecha_desde?: string
  fecha_hasta?: string
  colaborador_id?: number
  departamento_id?: number
  prioridad?: Prioridad
  page?: number
  per_page?: number
}

export interface OpcionesDnf {
  colaboradores: { id: number; nombre: string; puesto: string; departamento_id: number | null }[]
  departamentos: { id: number; nombre: string; colaboradores_evaluables: number }[]
}

export interface SolicitudDnf {
  alcance: Alcance
  colaborador_id?: number
  departamento_id?: number
}

export interface BrechaPrioritaria extends Brecha {
  /** Posición 1–3 entre las prioritarias. */
  posicion?: number
}

export interface Cumplida {
  competencia_id: number
  nombre: string
  nivel_actual: Nivel | null
  estado: EstadoBrecha
}

export interface MiDiagnostico {
  dnf: { id: number; codigo: string; fecha: string }
  puesto: { codigo: string; nombre: string } | null
  resumen: { con_brecha: number; cumplen: number; sin_evaluacion: number }
  prioritarias: Brecha[]
  brechas: Brecha[]
  cumplen: Cumplida[]
  /** competencia_id => tiene al menos un curso o micro-contenido disponible. */
  tiene_oferta: Record<string, boolean>
}

export interface PlanGenerado {
  plan_formacion_id: number
  generado_por: { id: number; nombre: string } | null
  generado_en: string
}

const BASE = '/api/dnf'

export async function generarDnf(solicitud: SolicitudDnf): Promise<DnfGenerada> {
  const response = await http.post<DnfGenerada>(BASE, solicitud)
  return response.data
}

export async function listarDnf(filtros: FiltrosHistorial): Promise<PaginaHistorial> {
  const params: Record<string, string | number> = {}
  if (filtros.fecha_desde) params.fecha_desde = filtros.fecha_desde
  if (filtros.fecha_hasta) params.fecha_hasta = filtros.fecha_hasta
  if (filtros.colaborador_id) params.colaborador_id = filtros.colaborador_id
  if (filtros.departamento_id) params.departamento_id = filtros.departamento_id
  if (filtros.prioridad) params.prioridad = filtros.prioridad
  if (filtros.page) params.page = filtros.page
  if (filtros.per_page) params.per_page = filtros.per_page
  const response = await http.get<PaginaHistorial>(BASE, { params })
  return response.data
}

export async function obtenerDnf(id: number, colaboradorId?: number): Promise<{ data: Dnf; avisos?: AvisosDnf }> {
  const response = await http.get<{ data: Dnf; avisos?: AvisosDnf }>(`${BASE}/${id}`, {
    params: colaboradorId ? { colaborador_id: colaboradorId } : undefined,
  })
  return response.data
}

export async function obtenerOpciones(): Promise<OpcionesDnf> {
  const response = await http.get<OpcionesDnf>(`${BASE}/opciones`)
  return response.data
}

export async function miDiagnostico(): Promise<MiDiagnostico | null> {
  const response = await http.get<{ data: MiDiagnostico | null }>(`${BASE}/mi-diagnostico`)
  return response.data.data
}

export async function generarPlan(id: number): Promise<PlanGenerado> {
  const response = await http.post<{ data: PlanGenerado }>(`${BASE}/${id}/plan`)
  return response.data.data
}

/** Destino del plan de formación generado desde una DNF (lo define la feature Plan de Formación). */
export function rutaDelPlan(planId: number): string {
  return `/plan-formacion/${planId}`
}
