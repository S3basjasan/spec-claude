import type { Kpis } from '../api'
import { pdiText } from '../text'

/** Indicadores del conjunto de planes: Borradores, Acordados, Cerrados e hitos vencidos. */
export function KpisDePdi({ kpis }: { kpis: Kpis }) {
  return (
    <section aria-label={pdiText.titulo} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      {(Object.keys(pdiText.lista.kpis) as (keyof Kpis)[]).map((clave) => (
        <div key={clave} className="rounded-xl border border-border bg-surface p-4">
          <p className="m-0 text-[13px] text-muted">{pdiText.lista.kpis[clave]}</p>
          <p className={`m-0 mt-1 text-2xl font-bold ${clave === 'hitos_vencidos' && kpis[clave] > 0 ? 'text-danger' : ''}`}>{kpis[clave]}</p>
        </div>
      ))}
    </section>
  )
}
