import type { AccionMiPlan } from '../api'
import { planText } from '../text'
import { ChipTipo } from './ChipTipo'

interface ProximasActividadesProps {
  acciones: AccionMiPlan[]
  onVerDetalle: (accion: AccionMiPlan) => void
}

/** Las tres próximas acciones no canceladas del colaborador, la más cercana primero. */
export function ProximasActividades({ acciones, onVerDetalle }: ProximasActividadesProps) {
  const t = planText.miPlan
  return (
    <section aria-labelledby="proximas-titulo" className="flex flex-col gap-3">
      <h2 id="proximas-titulo" className="text-lg font-bold">
        {t.proximas}
      </h2>
      {acciones.length === 0 ? (
        <p className="text-[15px] text-muted">{t.proximasVacio}</p>
      ) : (
        <ol className="grid gap-3 md:grid-cols-3">
          {acciones.map((accion, indice) => (
            <li key={accion.id} className="flex flex-col gap-2 rounded-xl border border-border bg-surface p-4">
              <span className="flex size-7 items-center justify-center rounded-full bg-soft text-[13px] font-bold text-accent" aria-hidden="true">
                {indice + 1}
              </span>
              <p className="text-base font-bold">{accion.nombre}</p>
              <p className="text-[13px] text-muted">{planText.rango(accion.fecha_inicio, accion.fecha_fin)}</p>
              <div>
                <ChipTipo tipo={accion.tipo} />
              </div>
              <button
                type="button"
                aria-label={`${t.verDetalle}: ${accion.nombre} (${t.proximas})`}
                onClick={() => onVerDetalle(accion)}
                className="mt-auto inline-flex min-h-11 items-center justify-center rounded-lg border border-border px-4 text-[15px] font-semibold hover:bg-soft"
              >
                {t.verDetalle}
              </button>
            </li>
          ))}
        </ol>
      )}
    </section>
  )
}
