export interface Kpi {
  label: string
  valor: string
  nota?: string
  /** Resalta el valor con el color de peligro cuando hay urgencia. */
  urgente?: boolean
}

/** Indicadores del diagnóstico (StatCard del design system): etiqueta, valor y nota. */
export function KpiDiagnostico({ items }: { items: Kpi[] }) {
  return (
    <dl className="grid gap-4" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))' }}>
      {items.map((kpi) => (
        <div key={kpi.label} className="rounded-xl border border-border bg-surface p-5">
          <dt className="text-sm text-muted">{kpi.label}</dt>
          <dd className={`mt-1 text-[32px] font-bold leading-tight ${kpi.urgente ? 'text-danger' : ''}`}>{kpi.valor}</dd>
          {kpi.nota && <dd className="text-[13px] text-muted">{kpi.nota}</dd>}
        </div>
      ))}
    </dl>
  )
}
