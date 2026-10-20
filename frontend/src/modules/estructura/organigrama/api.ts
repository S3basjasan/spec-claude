import { http } from '../../../shared/api/httpClient'

/** Solo llegan posiciones vigentes: las Suprimidas nunca salen del servidor. */
export type EstadoDePosicion = 'Ocupada' | 'Vacante'
/** Valores tal como los guarda el servidor (sin tilde); la interfaz muestra «Subrogación». */
export type ModalidadDePosicion = 'Nombramiento' | 'Contrato' | 'Encargo' | 'Subrogacion'

/** Posición vigente con todo lo que necesitan la tarjeta y el panel de detalle. */
export interface PosicionDelOrganigrama {
  id: number
  codigo: string
  estado: EstadoDePosicion
  modalidad: ModalidadDePosicion
  /** Nulo en la máxima autoridad. */
  posicion_jefe_id: number | null
  puesto: { id: number; codigo: string; nombre: string; descripcion: string }
  unidad: { id: number; codigo: string; nombre: string; tipo: { id: number; nombre: string; nivel: number }; estado: string }
  ocupante: { id: number; nombre: string | null } | null
}

/** Unidad activa para el selector del filtro. */
export interface UnidadDelFiltro {
  id: number
  codigo: string
  nombre: string
  nivel_arbol: number
  unidad_padre_id: number | null
}

export interface Organigrama {
  posiciones: PosicionDelOrganigrama[]
  unidades: UnidadDelFiltro[]
}

export async function obtenerOrganigrama(): Promise<Organigrama> {
  const response = await http.get<{ data: Organigrama }>('/api/organigrama')
  return response.data.data
}
