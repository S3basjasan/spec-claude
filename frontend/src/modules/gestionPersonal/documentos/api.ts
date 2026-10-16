import { http } from '../../../shared/api/httpClient'
import { obtenerPdf, type PdfObtenido } from '../../../shared/api/pdf'

export type EstadoDocumento = 'Disponible' | 'Archivado'

export interface CategoriaDeDocumento {
  id: number
  nombre: string
  /** Una categoría inactiva se identifica sin impedir el acceso permitido. */
  estado: 'Activa' | 'Inactiva'
}

/** Representación del Administrador. */
export interface Documento {
  id: number
  titulo: string
  estado: EstadoDocumento
  visible_colaborador: boolean
  categoria: CategoriaDeDocumento | null
  formato: string
  tamano_bytes: number
  version: number
  created_by: number
  created_at: string
  updated_by: number
  updated_at: string
  archivado_por: number | null
  archivado_en: string | null
}

/** Representación del Colaborador: sin visibilidad, estado ni auditoría. */
export interface MiDocumento {
  id: number
  titulo: string
  categoria: CategoriaDeDocumento | null
  formato: string
  tamano_bytes: number
  created_at: string
}

export interface PaginaDeDocumentos<T> {
  data: T[]
  meta: { current_page: number; per_page: number; total: number; last_page: number; from: number | null; to: number | null }
}

export interface FiltrosDocumentos {
  q?: string
  categoria_id?: number
  /** Solo el Administrador; la consulta propia siempre es de Disponibles. */
  estado?: EstadoDocumento
  page?: number
}

export const POR_PAGINA = 20
export const MAX_BYTES = 10_000_000

/** `colaboradorId` null = la propia (Mi ficha / Mis documentos). */
const base = (colaboradorId: number | null) => (colaboradorId === null ? '/api/mi-ficha/documentos' : `/api/colaboradores/${colaboradorId}/documentos`)

export async function listarDocumentos(colaboradorId: number, filtros: FiltrosDocumentos, signal?: AbortSignal): Promise<PaginaDeDocumentos<Documento>> {
  const params: Record<string, string | number> = { per_page: POR_PAGINA, estado: filtros.estado ?? 'Disponible' }
  if (filtros.q) params.q = filtros.q
  if (filtros.categoria_id) params.categoria_id = filtros.categoria_id
  if (filtros.page) params.page = filtros.page
  const respuesta = await http.get<PaginaDeDocumentos<Documento>>(base(colaboradorId), { params, signal })
  return respuesta.data
}

export async function listarMisDocumentos(filtros: FiltrosDocumentos, signal?: AbortSignal): Promise<PaginaDeDocumentos<MiDocumento>> {
  const params: Record<string, string | number> = { per_page: POR_PAGINA }
  if (filtros.q) params.q = filtros.q
  if (filtros.categoria_id) params.categoria_id = filtros.categoria_id
  if (filtros.page) params.page = filtros.page
  const respuesta = await http.get<PaginaDeDocumentos<MiDocumento>>(base(null), { params, signal })
  return respuesta.data
}

export interface DatosDeCarga {
  titulo: string
  categoria_id: number
  visible_colaborador: boolean
  archivo: File
}

export async function cargarDocumento(colaboradorId: number, datos: DatosDeCarga, claveDeOperacion: string): Promise<Documento> {
  const formulario = new FormData()
  formulario.append('titulo', datos.titulo)
  formulario.append('categoria_id', String(datos.categoria_id))
  formulario.append('visible_colaborador', datos.visible_colaborador ? '1' : '0')
  formulario.append('archivo', datos.archivo)
  const respuesta = await http.post<{ data: Documento }>(base(colaboradorId), formulario, { headers: { 'Idempotency-Key': claveDeOperacion } })
  return respuesta.data.data
}

export async function cambiarVisibilidad(colaboradorId: number, id: number, visible: boolean, version: number): Promise<Documento> {
  const respuesta = await http.patch<{ data: Documento }>(`${base(colaboradorId)}/${id}/visibilidad`, { visible_colaborador: visible, version })
  return respuesta.data.data
}

export async function archivarDocumento(colaboradorId: number, id: number, version: number): Promise<Documento> {
  const respuesta = await http.patch<{ data: Documento }>(`${base(colaboradorId)}/${id}/archivo`, { version })
  return respuesta.data.data
}

/** Contenido autorizado del PDF como blob; `descarga` fuerza `attachment`. */
export function obtenerContenido(colaboradorId: number | null, id: number, descarga: boolean): Promise<PdfObtenido> {
  return obtenerPdf(`${base(colaboradorId)}/${id}/contenido`, descarga ? { descarga: 1 } : undefined)
}
