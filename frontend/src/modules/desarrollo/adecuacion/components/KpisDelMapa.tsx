import { PREPARACIONES, type Preparacion } from '../api'
import { adecuacionText } from '../text'

const t = adecuacionText.mapa

/** Cuatro indicadores, uno por preparación (cantidad de filas vigentes y nota). */
export function KpisDelMapa({ kpis }: { kpis: Record<Preparacion, number> }) {
  return (
    <ul aria-label={t.kpis} className="m-0 grid list-none gap-3 p-0 [grid-template-columns:repeat(auto-fit,minmax(min(100%,200px),1fr))]">
      {PREPARACIONES.map((p) => (
        <li key={p} className="rounded-xl border border-border bg-surface px-4 py-3">
          <div className="text-[13px] text-muted">{adecuacionText.preparacion[p]}</div>
          <div className="text-[26px] font-bold tabular-nums">{kpis[p]}</div>
          <div className="text-xs text-muted">{t.kpiNota[p]}</div>
        </li>
      ))}
    </ul>
  )
}
