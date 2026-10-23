import type { Pdi } from '../api'
import { rangoDeFechas } from '../fecha'
import { pdiText } from '../text'
import { BadgeDeEstadoDePdi, BadgeDeTipoDePdi, BarraDeAvance } from './Etiquetas'

interface TarjetaDePlanProps {
  plan: Pdi
  seleccionado: boolean
  onElegir: () => void
}

/** Tarjeta de «Mi plan de desarrollo»: tipo, estado, destino, avance y periodo. */
export function TarjetaDePlan({ plan, seleccionado, onElegir }: TarjetaDePlanProps) {
  return (
    <button
      type="button"
      onClick={onElegir}
      aria-pressed={seleccionado}
      className={`flex min-h-11 flex-col gap-2 rounded-xl border p-4 text-left transition-colors ${seleccionado ? 'border-accent bg-soft' : 'border-border bg-surface hover:bg-soft'}`}
    >
      <span className="flex flex-wrap items-center gap-2">
        <BadgeDeTipoDePdi valor={plan.tipo} />
        <BadgeDeEstadoDePdi valor={plan.estado} />
        <span className="text-[13px] text-muted">{plan.codigo}</span>
      </span>
      <span className="font-semibold">{plan.puesto_objetivo?.nombre ?? pdiText.destino(null)}</span>
      <BarraDeAvance valor={plan.avance} />
      <span className="text-[13px] text-muted">{rangoDeFechas(plan.fecha_inicio, plan.fecha_fin)}</span>
    </button>
  )
}
