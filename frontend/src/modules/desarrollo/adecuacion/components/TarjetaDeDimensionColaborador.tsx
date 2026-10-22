import { etiquetaDeBarrasPropias, fraccionesDe, resumenDe } from '../barras'
import type { Dimension, Resultado } from '../api'
import { adecuacionText, formatearDiferencia, formatearPorcentaje } from '../text'
import { BarrasTriples } from './BarrasTriples'

interface Props {
  dimension: Dimension
  resultados: Resultado[]
  /** Cumplimiento 0..1 de la dimensión frente al puesto objetivo; `null` si no se evaluó. */
  cumplimiento: number | null
}

/** Tarjeta de una dimensión en «Mi adecuación»: porcentaje, diferencia por requisito y dos barras (tu nivel y el requisito). */
export function TarjetaDeDimensionColaborador({ dimension, resultados, cumplimiento }: Props) {
  const t = adecuacionText.panel
  const titulo = adecuacionText.dimension[dimension]

  return (
    <section aria-label={titulo} className="flex flex-col gap-3.5 rounded-xl border border-border bg-surface px-[18px] py-4">
      <div className="flex items-baseline justify-between gap-2.5">
        <h2 className="m-0 text-[17px] font-semibold">{titulo}</h2>
        <span className="text-sm font-bold tabular-nums">{formatearPorcentaje(cumplimiento)}</span>
      </div>

      {resultados.length === 0 ? (
        <p className="m-0 text-sm text-muted">{dimension === 'Conocimiento' ? t.desactivada : dimension === 'Experiencia' ? t.noEvaluada : t.sinRequisitos}</p>
      ) : (
        <ul className="m-0 flex list-none flex-col gap-3.5 p-0">
          {resultados.map((r) => {
            const { persona, futuro } = fraccionesDe(r)

            return (
              <li key={`${r.dimension}:${r.elemento_id ?? 0}`}>
                <div className="flex justify-between gap-2 text-sm font-semibold">
                  <span>{r.nombre ?? t.experiencia}</span>
                  <span className="tabular-nums" aria-label={r.diferencia === null ? undefined : `Diferencia ${formatearDiferencia(r.diferencia)}`}>
                    {r.diferencia === null ? t.sinValor : formatearDiferencia(r.diferencia)}
                  </span>
                </div>
                <div className="mb-1.5 mt-0.5 text-xs text-muted">{resumenDe(r)}</div>
                <BarrasTriples persona={persona} futuro={futuro} etiqueta={etiquetaDeBarrasPropias(r)} />
              </li>
            )
          })}
        </ul>
      )}
    </section>
  )
}
