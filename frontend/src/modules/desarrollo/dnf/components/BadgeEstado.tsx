import type { EstadoBrecha } from '../api'
import { dnfText } from '../text'

const estilos: Record<EstadoBrecha, string> = {
  ConBrecha: 'bg-dangersoft text-danger',
  Cumple: 'bg-soft text-accent',
  Supera: 'bg-infosoft text-info',
  SinEvaluacion: 'bg-neutralsoft text-neutral',
}

/** Estado de una brecha: siempre con texto, nunca solo con color. */
export function BadgeEstado({ estado }: { estado: EstadoBrecha }) {
  return (
    <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[13px] font-bold ${estilos[estado]}`}>
      {dnfText.estado[estado]}
    </span>
  )
}
