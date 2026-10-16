import type { Prioridad } from '../api'
import { dnfText } from '../text'

const estilos: Record<Prioridad, string> = {
  Critica: 'bg-dangersoft text-danger',
  Alta: 'bg-warnsoft text-warn',
  Media: 'bg-infosoft text-info',
  Baja: 'bg-neutralsoft text-neutral',
}

/** Prioridad de una brecha: siempre con texto, nunca solo con color (design/DESIGN.md §4). */
export function BadgePrioridad({ prioridad }: { prioridad: Prioridad | null }) {
  if (prioridad === null) {
    return <span aria-label={dnfText.sinPrioridadAria}>{dnfText.sinPrioridad}</span>
  }
  return (
    <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[13px] font-bold ${estilos[prioridad]}`}>
      {dnfText.prioridad[prioridad]}
    </span>
  )
}
