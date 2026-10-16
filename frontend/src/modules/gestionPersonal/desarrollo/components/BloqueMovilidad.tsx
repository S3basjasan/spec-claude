import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import type { Movilidad } from '../api'
import { formatearFecha } from '../fecha'
import { desarrolloText } from '../text'

const text = desarrolloText.movilidad

interface BloqueMovilidadProps {
  movilidad: Movilidad
  editable: boolean
  onActualizar: () => void
}

/** Disponibilidad declarada; no sugiere traslados, vacantes ni aprobaciones. */
export function BloqueMovilidad({ movilidad, editable, onActualizar }: BloqueMovilidadProps) {
  const observaciones = movilidad.observaciones ?? (movilidad.estado === 'NoInformada' ? text.notInformed : text.noNotes)

  return (
    <section aria-label={text.title} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-base font-bold">{text.title}</h2>
        {editable && (
          <Button variant="secondary" onClick={onActualizar}>
            {text.update}
          </Button>
        )}
      </div>
      <div className="flex flex-wrap items-center gap-3">
        <Badge variant={movilidad.estado === 'Disponible' ? 'default' : 'neutral'}>{desarrolloText.estadosMovilidad[movilidad.estado]}</Badge>
        {movilidad.actualizada_at && <span className="text-sm text-muted">{text.updatedOn(formatearFecha(movilidad.actualizada_at))}</span>}
      </div>
      <p className="text-sm">{observaciones}</p>
      <p className="text-[13px] text-muted">{text.note}</p>
    </section>
  )
}
