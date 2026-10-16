import { http } from '../../../shared/api/httpClient'

export type EstadoFormacion = 'EnCurso' | 'Completada' | 'Incompleta'
export type EstadoMovilidad = 'Disponible' | 'NoDisponible' | 'NoInformada'

export interface Formacion {
  id: number
  nivel: string
  titulo_programa: string
  institucion: string
  estado: EstadoFormacion
  fecha_inicio: string | null
  fecha_finalizacion: string | null
  observaciones: string | null
  version: number
  created_at: string
  updated_at: string
}

export interface Experiencia {
  id: number
  organizacion: string
  cargo: string
  fecha_inicio: string
  fecha_finalizacion: string | null
  funciones: string | null
  observaciones: string | null
  version: number
  created_at: string
  updated_at: string
}

export interface Movilidad {
  estado: EstadoMovilidad
  observaciones: string | null
  actualizada_at: string | null
  version: number
}

export interface Desarrollo {
  colaborador_id: number
  /** Fecha de hoy según la zona del tenant: referencia para validar fechas en el cliente. */
  hoy: string
  formaciones: Formacion[]
  experiencias: Experiencia[]
  movilidad: Movilidad
  acciones: { editar: boolean }
}

/** Campos editables de una formación, tal como viaja en el cuerpo. */
export interface DatosFormacion {
  nivel: string
  titulo_programa: string
  institucion: string
  estado: EstadoFormacion
  fecha_inicio: string | null
  fecha_finalizacion: string | null
  observaciones: string | null
}

export interface DatosExperiencia {
  organizacion: string
  cargo: string
  fecha_inicio: string
  fecha_finalizacion: string | null
  funciones: string | null
  observaciones: string | null
}

export interface DatosMovilidad {
  estado: EstadoMovilidad
  observaciones: string | null
}

/** `colaboradorId` null = la propia (Mi ficha, solo lectura). */
export async function obtenerDesarrollo(colaboradorId: number | null): Promise<Desarrollo> {
  const url = colaboradorId === null ? '/api/mi-ficha/desarrollo' : `/api/colaboradores/${colaboradorId}/desarrollo`
  const response = await http.get<{ data: Desarrollo }>(url)
  return response.data.data
}

export async function crearFormacion(colaboradorId: number, datos: DatosFormacion): Promise<Formacion> {
  const response = await http.post<{ data: Formacion }>(`/api/colaboradores/${colaboradorId}/formaciones`, datos)
  return response.data.data
}

export async function corregirFormacion(colaboradorId: number, id: number, datos: DatosFormacion, version: number): Promise<Formacion> {
  const response = await http.patch<{ data: Formacion }>(`/api/colaboradores/${colaboradorId}/formaciones/${id}`, { ...datos, version })
  return response.data.data
}

export async function crearExperiencia(colaboradorId: number, datos: DatosExperiencia): Promise<Experiencia> {
  const response = await http.post<{ data: Experiencia }>(`/api/colaboradores/${colaboradorId}/experiencias`, datos)
  return response.data.data
}

export async function corregirExperiencia(colaboradorId: number, id: number, datos: DatosExperiencia, version: number): Promise<Experiencia> {
  const response = await http.patch<{ data: Experiencia }>(`/api/colaboradores/${colaboradorId}/experiencias/${id}`, { ...datos, version })
  return response.data.data
}

export async function actualizarMovilidad(colaboradorId: number, datos: DatosMovilidad, version: number): Promise<Movilidad> {
  const response = await http.patch<{ data: Movilidad }>(`/api/colaboradores/${colaboradorId}/movilidad`, { ...datos, version })
  return response.data.data
}
