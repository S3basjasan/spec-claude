import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import type { AccionMiPlan } from '../api'
import { planText } from '../text'
import { BadgeEstadoAccion } from './BadgeEstadoAccion'
import { ChipTipo } from './ChipTipo'

const t = planText.miPlan.detalle

/** Detalle de solo lectura de una acción del colaborador (sin lugar ni avance por acción: no están en la spec). */
export function DetalleMiAccion({ accion, onClose }: { accion: AccionMiPlan | null; onClose: () => void }) {
  if (accion === null) return null
  return (
    <Modal open title={accion.nombre} closeLabel={t.cerrar} onClose={onClose} footer={<Button onClick={onClose}>{t.cerrar}</Button>}>
      <div className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center gap-2">
          <ChipTipo tipo={accion.tipo} />
          <BadgeEstadoAccion estado={accion.estado_accion} />
        </div>
        <dl className="grid gap-3 sm:grid-cols-2">
          {[
            [t.competencia, accion.competencias.map((c) => c.nombre).join(', ') || planText.sinDato],
            [t.fechas, planText.rango(accion.fecha_inicio, accion.fecha_fin)],
            [t.formador, accion.formador ?? planText.sinDato],
            [t.duracion, planText.horas(accion.duracion_horas)],
            [t.plan, `${accion.plan.nombre} (${accion.plan.anio})`],
          ].map(([etiqueta, valor]) => (
            <div key={etiqueta}>
              <dt className="text-[13px] text-muted">{etiqueta}</dt>
              <dd className="text-[15px] font-semibold">{valor}</dd>
            </div>
          ))}
        </dl>
        {accion.estado_accion === 'Cancelada' && <p className="text-[13px] text-danger">{planText.miPlan.canceladaMotivo(accion.motivo_cancelacion)}</p>}
      </div>
    </Modal>
  )
}
