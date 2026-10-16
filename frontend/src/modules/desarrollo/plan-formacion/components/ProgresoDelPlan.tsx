import { planText } from '../text'

interface ProgresoDelPlanProps {
  porcentaje: number
  completadas: number
  asignadas: number
}

/** Barra «% del plan completado» (design/DESIGN.md §4): acciones completadas sobre asignadas no canceladas. */
export function ProgresoDelPlan({ porcentaje, completadas, asignadas }: ProgresoDelPlanProps) {
  const t = planText.miPlan
  return (
    <section aria-labelledby="progreso-titulo" className="rounded-xl border border-border bg-surface p-5">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="progreso-titulo" className="text-lg font-bold">
          {t.progreso}
        </h2>
        <p className="text-2xl font-bold">{porcentaje} %</p>
      </div>
      <div
        role="progressbar"
        aria-label={t.progresoLabel}
        aria-valuenow={porcentaje}
        aria-valuemin={0}
        aria-valuemax={100}
        className="mt-3 h-3 overflow-hidden rounded-full bg-border"
      >
        <div className="h-full rounded-full bg-accent" style={{ width: `${porcentaje}%` }} />
      </div>
      <p className="mt-2 text-[13px] text-muted">{t.progresoNota(completadas, asignadas)}</p>
    </section>
  )
}
