import type { ResumenProgreso } from '../api'
import { horas, numero } from '../formato'
import { reportesText } from '../text'

const t = reportesText.colaborador.tarjetas

/** Resumen del colaborador: formaciones completadas, competencias adquiridas, horas acumuladas y certificados. */
export function TarjetasProgreso({ resumen }: { resumen: ResumenProgreso }) {
  const tarjetas = [
    { clave: 'formaciones', ...t.formaciones, valor: numero(resumen.formaciones_completadas) },
    { clave: 'competencias', ...t.competencias, valor: numero(resumen.competencias_adquiridas) },
    { clave: 'horas', ...t.horas, valor: horas(resumen.horas_acumuladas) },
    { clave: 'certificados', ...t.certificados, valor: numero(resumen.certificados) },
  ]

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
      {tarjetas.map((c) => (
        <article key={c.clave} aria-label={c.etiqueta} className="flex flex-col gap-1.5 rounded-xl border border-border bg-surface p-4">
          <h2 className="m-0 text-[13px] font-semibold uppercase tracking-wide text-muted">{c.etiqueta}</h2>
          <p className="m-0 text-[32px] font-bold leading-none tracking-tight">{c.valor}</p>
          <p className="m-0 text-[13px] text-muted">{c.nota}</p>
        </article>
      ))}
    </div>
  )
}
