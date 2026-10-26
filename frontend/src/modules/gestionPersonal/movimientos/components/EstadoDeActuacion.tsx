import { Badge } from '../../../../shared/ui/Badge'
import type { EstadoActuacion } from '../api'
import { movimientosText } from '../text'

const VARIANTES: Record<EstadoActuacion, 'default' | 'neutral' | 'warning' | 'danger'> = {
  Presentada: 'warning',
  EnRevision: 'warning',
  Aprobada: 'default',
  Rechazada: 'danger',
  Retirada: 'neutral',
  Programada: 'default',
  Aplicada: 'default',
  IncidenciaDeAplicacion: 'danger',
  Informada: 'warning',
  Revisada: 'default',
  Justificada: 'default',
}

/** Estado distinguible por texto (no solo por color): pendiente, rechazado, aprobado, programado, aplicado o incidencia. */
export function EstadoDeActuacion({ estado }: { estado: EstadoActuacion }) {
  return <Badge variant={VARIANTES[estado]}>{movimientosText.estados[estado]}</Badge>
}
