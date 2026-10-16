import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import type { CampoFaltante } from '../api'
import { usePlan } from '../hooks/usePlanes'
import { planText } from '../text'

export interface Faltante {
  accion_id: number | null
  campo: CampoFaltante
}

interface FaltantesDialogProps {
  planId: number
  faltantes: Faltante[] | null
  onClose: () => void
}

/** Lista lo que falta para enviar un plan a revisión, agrupado por acción. */
export function FaltantesDialog({ planId, faltantes, onClose }: FaltantesDialogProps) {
  const plan = usePlan(faltantes === null ? null : planId)
  if (faltantes === null) return null

  const nombreDe = (accionId: number) => plan.data?.acciones.find((a) => a.id === accionId)?.nombre ?? `#${accionId}`

  return (
    <Modal open title={planText.transicion.faltantesTitle} onClose={onClose} footer={<Button onClick={onClose}>{planText.acciones.detalle.cerrar}</Button>}>
      <ul className="flex flex-col gap-1.5 text-[15px]">
        {faltantes.map((f, indice) => (
          <li key={`${f.accion_id ?? 'plan'}-${f.campo}-${indice}`}>
            {f.accion_id === null ? planText.campoFaltante[f.campo] : `${planText.transicion.faltantesAccion(nombreDe(f.accion_id))}: ${planText.campoFaltante[f.campo]}`}
          </li>
        ))}
      </ul>
    </Modal>
  )
}
