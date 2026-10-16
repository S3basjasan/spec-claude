import { ORDEN_PRIORIDAD, type Brecha, type EstadoBrecha, type Prioridad } from './api'

export interface FiltrosDiagnostico {
  prioridad: Prioridad | ''
  estado: EstadoBrecha | ''
  soloConBrecha: boolean
}

export const SIN_FILTROS: FiltrosDiagnostico = { prioridad: '', estado: '', soloConBrecha: false }

/** Prioridad (mayor primero), luego mayor diferencia en grados (más negativa primero) y luego nombre. */
export function ordenarBrechas(brechas: Brecha[]): Brecha[] {
  return [...brechas].sort((a, b) => {
    const prioridad = (b.prioridad ? ORDEN_PRIORIDAD[b.prioridad] : 0) - (a.prioridad ? ORDEN_PRIORIDAD[a.prioridad] : 0)
    if (prioridad !== 0) return prioridad
    if (a.diferencia !== b.diferencia) {
      if (a.diferencia === null) return 1
      if (b.diferencia === null) return -1
      return a.diferencia - b.diferencia
    }
    return a.nombre.localeCompare(b.nombre, 'es')
  })
}

export function filtrarBrechas(brechas: Brecha[], filtros: FiltrosDiagnostico): Brecha[] {
  return brechas.filter(
    (b) =>
      (filtros.prioridad === '' || b.prioridad === filtros.prioridad) &&
      (filtros.estado === '' || b.estado === filtros.estado) &&
      (!filtros.soloConBrecha || b.estado === 'ConBrecha'),
  )
}
