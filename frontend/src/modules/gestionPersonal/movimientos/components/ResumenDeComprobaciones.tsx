import { IconAlertTriangle, IconCheck, IconInfoCircle } from '../../../../shared/ui/icons'
import type { Comprobacion, EstadoDeComprobacion } from '../api'
import { movimientosText } from '../text'

const text = movimientosText.detail

const TONOS: Record<EstadoDeComprobacion, string> = {
  Cumple: 'bg-soft text-accent',
  Pendiente: 'bg-warningsoft text-warning',
  NoCumple: 'bg-dangersoft text-danger',
  NoVerificable: 'bg-dangersoft text-danger',
}

function Icono({ estado }: { estado: EstadoDeComprobacion }) {
  if (estado === 'Cumple') return <IconCheck size={16} />
  if (estado === 'Pendiente') return <IconInfoCircle size={16} />
  return <IconAlertTriangle size={16} />
}

interface ResumenDeComprobacionesProps {
  comprobaciones: Comprobacion[]
  /** La solicitud ya no admite resolución: no hay comprobaciones vigentes que mostrar. */
  resoluble: boolean
}

/**
 * Síntesis verificable de condiciones antes de decidir. Separa cumplida, pendiente, incumplida y no verificable: un
 * error de política, evaluación, estructura o documentos nunca se presenta como «cumple» ni como lista vacía.
 */
export function ResumenDeComprobaciones({ comprobaciones, resoluble }: ResumenDeComprobacionesProps) {
  if (!resoluble) return <p className="text-sm text-muted">{text.checksNone}</p>

  return (
    <ul aria-label={text.checks} className="flex flex-col gap-2">
      {comprobaciones.map((c) => (
        <li key={c.clave} className="flex flex-col gap-1 rounded-lg border border-border bg-surface p-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <span className="font-semibold">{text.checkNames[c.clave]}</span>
            <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-[3px] text-[13px] font-semibold ${TONOS[c.estado]}`}>
              <Icono estado={c.estado} />
              {text.checkStates[c.estado]}
            </span>
          </div>
          <p className="text-sm text-muted">{c.texto}</p>
        </li>
      ))}
    </ul>
  )
}
