import { etiquetaDeBarras, fraccionesDe, resumenDe } from '../barras'
import type { Dimension, Resultado } from '../api'
import { adecuacionText, formatearPorcentaje } from '../text'
import { BarrasTriples } from './BarrasTriples'
import { EstadoResultadoBadge } from './Etiquetas'

interface Props {
  dimension: Dimension
  resultados: Resultado[]
  /** Cumplimiento 0..1 frente al puesto actual y frente al objetivo; `null` si la dimensión no se evaluó. */
  cumplimientoActual: number | null
  cumplimientoObjetivo: number | null
  /** Por qué no hay filas: la dimensión está desactivada (conocimientos) o no se indicó la experiencia requerida. */
  motivoSinFilas: 'desactivada' | 'noEvaluada' | null
}

/** Panel de una dimensión (Experiencia, Conocimientos o Competencias) con sus requisitos y las tres barras de cada uno. */
export function PanelDeDimension({ dimension, resultados, cumplimientoActual, cumplimientoObjetivo, motivoSinFilas }: Props) {
  const t = adecuacionText.panel
  const titulo = adecuacionText.dimension[dimension]

  return (
    <section aria-label={titulo} className="flex flex-col gap-3.5 rounded-xl border border-border bg-surface px-[18px] py-4">
      <div className="flex items-baseline justify-between gap-2.5">
        <h2 className="m-0 text-[17px] font-semibold">{titulo}</h2>
        <span className="text-xs text-muted">{t.cabecera(formatearPorcentaje(cumplimientoActual), formatearPorcentaje(cumplimientoObjetivo))}</span>
      </div>

      {resultados.length === 0 ? (
        <p className="m-0 text-sm text-muted">{motivoSinFilas === 'desactivada' ? t.desactivada : motivoSinFilas === 'noEvaluada' ? t.noEvaluada : t.sinRequisitos}</p>
      ) : (
        <ul className="m-0 flex list-none flex-col gap-3.5 p-0">
          {resultados.map((r) => {
            const { actual, persona, futuro } = fraccionesDe(r)

            return (
              <li key={`${r.dimension}:${r.elemento_id ?? 0}`}>
                <div className="flex justify-between gap-2 text-sm font-semibold">
                  <span>{r.nombre ?? t.experiencia}</span>
                  <EstadoResultadoBadge valor={r.estado} />
                </div>
                <div className="mb-1.5 mt-0.5 text-xs text-muted">{resumenDe(r)}</div>
                <BarrasTriples actual={actual} persona={persona} futuro={futuro} etiqueta={etiquetaDeBarras(r)} />
              </li>
            )
          })}
        </ul>
      )}
    </section>
  )
}
