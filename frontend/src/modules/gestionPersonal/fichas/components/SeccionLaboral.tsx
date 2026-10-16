import { Button } from '../../../../shared/ui/Button'
import type { BloqueLaboral } from '../api'
import { fichasText } from '../text'
import { DatoOpcional } from './DatoOpcional'
import { ProximoIngreso } from './ProximoIngreso'

const labels = fichasText.detail.labels

/** Aviso de fuente caída con Reintentar; distinto de «No registrado» (ausencia confirmada). */
export function NoDisponible({ onReintentar }: { onReintentar: () => void }) {
  return (
    <div role="status" className="flex flex-wrap items-center gap-3 text-sm text-muted">
      <span>{fichasText.unavailable}</span>
      <Button variant="secondary" onClick={onReintentar}>
        {fichasText.retry}
      </Button>
    </div>
  )
}

interface SeccionLaboralProps {
  laboral: BloqueLaboral
  onReintentar: () => void
}

/** Puesto, unidad y estado vigentes; solo lectura (provienen de GP-02 y Estructura). */
export function SeccionLaboral({ laboral, onReintentar }: SeccionLaboralProps) {
  if (laboral.disponibilidad === 'no_disponible') {
    return <NoDisponible onReintentar={onReintentar} />
  }

  const estado = laboral.estado === null ? null : (fichasText.statuses[laboral.estado] ?? laboral.estado)

  return (
    <>
      <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div className="flex flex-col gap-0.5">
        <dt className="text-[13px] font-semibold text-muted">{labels.position}</dt>
        <dd className="text-[15px]">
          <DatoOpcional valor={laboral.puesto} />
        </dd>
      </div>
      <div className="flex flex-col gap-0.5">
        <dt className="text-[13px] font-semibold text-muted">{labels.unit}</dt>
        <dd className="text-[15px]">
          <DatoOpcional valor={laboral.unidad} />
        </dd>
      </div>
      <div className="flex flex-col gap-0.5">
        <dt className="text-[13px] font-semibold text-muted">{labels.status}</dt>
        <dd className="text-[15px]">
          <DatoOpcional valor={estado} />
        </dd>
      </div>
      </dl>
      {laboral.proximo_ingreso && <ProximoIngreso ingreso={laboral.proximo_ingreso} />}
    </>
  )
}
