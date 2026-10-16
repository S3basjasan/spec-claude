import type { EstadoAccion } from '../api'
import { planText } from '../text'

const estilos: Record<EstadoAccion, string> = {
  Programada: 'bg-infosoft text-info',
  EnCurso: 'bg-soft text-accent',
  Finalizada: 'bg-neutralsoft text-neutral',
  Cancelada: 'bg-dangersoft text-danger',
}

/** Estado calculado de una acción: siempre con texto (design/DESIGN.md §4). */
export function BadgeEstadoAccion({ estado }: { estado: EstadoAccion }) {
  return (
    <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[13px] font-bold ${estilos[estado]}`}>
      {planText.estadoAccion[estado]}
    </span>
  )
}
