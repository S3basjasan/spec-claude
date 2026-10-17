import type { ResumenAprendizaje as Resumen } from '../api'
import { microText } from '../text'

/** Tarjetas de resumen (spec 4.6): completados, en progreso, horas acumuladas y certificados. */
export function ResumenAprendizaje({ resumen }: { resumen: Resumen }) {
  const t = microText.colab.summary
  const horas = new Intl.NumberFormat('es-EC', { maximumFractionDigits: 1 }).format(resumen.horas_acumuladas)
  const tarjetas: { clave: keyof typeof t; valor: string }[] = [
    { clave: 'completados', valor: String(resumen.completados) },
    { clave: 'en_progreso', valor: String(resumen.en_progreso) },
    { clave: 'horas_acumuladas', valor: horas },
    { clave: 'certificados', valor: String(resumen.certificados) },
  ]

  return (
    <dl aria-label={microText.colab.summaryLabel} className="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(180px,1fr))]">
      {tarjetas.map((tarjeta) => (
        <div key={tarjeta.clave} className="rounded-xl border border-border bg-surface p-5">
          <dt className="text-sm text-muted">{t[tarjeta.clave]}</dt>
          <dd className="mt-1 text-[32px] font-bold leading-none">{tarjeta.valor}</dd>
        </div>
      ))}
    </dl>
  )
}
