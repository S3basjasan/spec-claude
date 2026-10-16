import type { EstadoPlan } from '../api'
import { planText } from '../text'

const estilos: Record<EstadoPlan, string> = {
  Borrador: 'bg-neutralsoft text-neutral',
  Revision: 'bg-warnsoft text-warn',
  Aprobado: 'bg-infosoft text-info',
  Ejecutando: 'bg-soft text-accent',
  Cerrado: 'border border-border bg-neutralsoft text-neutral',
}

/** Estado del plan: siempre con texto, nunca solo con color (design/DESIGN.md §4). */
export function BadgeEstadoPlan({ estado }: { estado: EstadoPlan }) {
  return (
    <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[13px] font-bold ${estilos[estado]}`}>
      {planText.estadoPlan[estado]}
    </span>
  )
}
