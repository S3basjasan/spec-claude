import type { AccionMiPlan } from '../api'
import { planText } from '../text'
import { BadgeEstadoAccion } from './BadgeEstadoAccion'
import { ChipTipo } from './ChipTipo'

interface TarjetaAccionProps {
  accion: AccionMiPlan
  onVerDetalle: (accion: AccionMiPlan) => void
}

/** Tarjeta de una acción del colaborador: nombre, competencia, fechas, formador, estado y «Ver detalle». */
export function TarjetaAccion({ accion, onVerDetalle }: TarjetaAccionProps) {
  const t = planText.miPlan
  return (
    <article aria-label={accion.nombre} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <h3 className="text-base font-bold">{accion.nombre}</h3>
        <div className="flex flex-wrap items-center gap-2">
          <ChipTipo tipo={accion.tipo} />
          <BadgeEstadoAccion estado={accion.estado_accion} />
        </div>
      </div>
      <dl className="grid gap-2 text-[15px] sm:grid-cols-3">
        <div>
          <dt className="text-[13px] text-muted">{t.detalle.competencia}</dt>
          <dd className="font-semibold">{accion.competencias.map((c) => c.nombre).join(', ') || planText.sinDato}</dd>
        </div>
        <div>
          <dt className="text-[13px] text-muted">{t.detalle.fechas}</dt>
          <dd className="font-semibold">{planText.rango(accion.fecha_inicio, accion.fecha_fin)}</dd>
        </div>
        <div>
          <dt className="text-[13px] text-muted">{t.detalle.formador}</dt>
          <dd className="font-semibold">{accion.formador ?? planText.sinDato}</dd>
        </div>
      </dl>
      {accion.estado_accion === 'Cancelada' && <p className="text-[13px] text-danger">{t.canceladaMotivo(accion.motivo_cancelacion)}</p>}
      <div>
        <button
          type="button"
          aria-label={`${t.verDetalle}: ${accion.nombre}`}
          onClick={() => onVerDetalle(accion)}
          className="inline-flex min-h-11 items-center rounded-lg border border-border px-4 text-[15px] font-semibold hover:bg-soft"
        >
          {t.verDetalle}
        </button>
      </div>
    </article>
  )
}
