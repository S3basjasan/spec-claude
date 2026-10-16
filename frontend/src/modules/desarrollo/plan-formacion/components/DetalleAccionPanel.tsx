import { Link } from 'react-router-dom'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import type { FilaAccion } from '../api'
import { planText } from '../text'
import { BadgeEstadoAccion } from './BadgeEstadoAccion'
import { BadgeEstadoPlan } from './BadgeEstadoPlan'
import { ChipTipo } from './ChipTipo'

const t = planText.acciones.detalle
const d = planText.detalle

interface DetalleAccionPanelProps {
  /** Acción a mostrar; `null` cierra el panel. */
  accion: FilaAccion | null
  onClose: () => void
}

/** Detalle de una acción (al hacer clic en el calendario o en la lista), con enlace al plan. */
export function DetalleAccionPanel({ accion, onClose }: DetalleAccionPanelProps) {
  if (accion === null) return null

  return (
    <Modal
      open
      title={accion.nombre}
      closeLabel={t.cerrar}
      onClose={onClose}
      footer={
        <>
          <Link to={`/plan-formacion/${accion.plan.id}`} className="inline-flex min-h-11 items-center rounded-lg border border-border px-5 font-semibold hover:bg-soft">
            {t.verPlan}
          </Link>
          <Button onClick={onClose}>{t.cerrar}</Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center gap-2">
          <ChipTipo tipo={accion.tipo} />
          <BadgeEstadoAccion estado={accion.estado_accion} />
          <BadgeEstadoPlan estado={accion.plan.estado} />
        </div>
        <dl className="grid gap-3 sm:grid-cols-2">
          {[
            [d.competencias, accion.competencias.map((c) => c.nombre).join(', ') || planText.sinDato],
            [d.fechas, planText.rango(accion.fecha_inicio, accion.fecha_fin)],
            [d.formador, accion.formador ?? planText.sinDato],
            [d.inscritos, planText.cupo(accion.inscritos, accion.capacidad_maxima)],
            [d.costo, planText.dinero(accion.costo)],
            [planText.acciones.columnas.plan, `${accion.plan.codigo} · ${accion.plan.nombre}`],
          ].map(([etiqueta, valor]) => (
            <div key={etiqueta}>
              <dt className="text-[13px] text-muted">{etiqueta}</dt>
              <dd className="text-[15px] font-semibold">{valor}</dd>
            </div>
          ))}
        </dl>
        {accion.cancelada && <p className="text-[13px] text-danger">{d.motivoCancelacion(accion.motivo_cancelacion)}</p>}
      </div>
    </Modal>
  )
}
