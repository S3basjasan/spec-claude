import { Badge } from '../../../../shared/ui/Badge'
import type { EstadoDeCierre, EstadoDeEntrevista, EstadoDeSalida } from '../api'
import { offboardingText } from '../text'

const VARIANTES_SALIDA: Record<EstadoDeSalida, 'default' | 'neutral' | 'warning' | 'danger'> = {
  Programada: 'warning',
  Efectiva: 'default',
  Cancelada: 'neutral',
  IncidenciaDeAplicacion: 'danger',
}

/** Estado de la salida distinguible por texto (no solo por color): programada, efectiva, cancelada o con incidencia. */
export function EstadoDeLaSalida({ estado }: { estado: EstadoDeSalida }) {
  return <Badge variant={VARIANTES_SALIDA[estado]}>{offboardingText.estados[estado]}</Badge>
}

export function EstadoDelCierre({ estado }: { estado: EstadoDeCierre }) {
  return <Badge variant={estado === 'Cerrado' ? 'default' : 'neutral'}>{offboardingText.cierre[estado]}</Badge>
}

const VARIANTES_ENTREVISTA: Record<EstadoDeEntrevista, 'default' | 'neutral' | 'warning' | 'danger'> = {
  Pendiente: 'warning',
  EnCurso: 'warning',
  Completada: 'default',
  NoRealizada: 'neutral',
}

export function EstadoDeLaEntrevista({ estado }: { estado: EstadoDeEntrevista }) {
  return <Badge variant={VARIANTES_ENTREVISTA[estado]}>{offboardingText.entrevista[estado]}</Badge>
}
