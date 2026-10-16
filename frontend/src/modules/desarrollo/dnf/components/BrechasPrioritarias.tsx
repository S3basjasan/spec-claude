import type { Brecha, Cumplida } from '../api'
import { dnfText } from '../text'
import { TarjetaBrecha } from './TarjetaBrecha'

const text = dnfText.colaborador

interface BrechasPrioritariasProps {
  prioritarias: Brecha[]
  brechas: Brecha[]
  cumplen: Cumplida[]
  tieneOferta: Record<string, boolean>
}

/** «Mis brechas prioritarias» (las tres de mayor atención), el resto de brechas y las competencias que ya cumple. */
export function BrechasPrioritarias({ prioritarias, brechas, cumplen, tieneOferta }: BrechasPrioritariasProps) {
  const idsPrioritarias = new Set(prioritarias.map((b) => b.competencia_id))
  const otras = brechas.filter((b) => !idsPrioritarias.has(b.competencia_id))

  return (
    <div className="flex flex-col gap-8">
      {prioritarias.length > 0 && (
        <section aria-labelledby="dnf-prioritarias" className="flex flex-col gap-4">
          <div>
            <h2 id="dnf-prioritarias" className="text-xl font-bold">
              {text.prioritarias.titulo}
            </h2>
            <p className="mt-1 text-sm text-muted">{text.prioritarias.descripcion}</p>
          </div>
          <ol className="grid gap-4 md:grid-cols-3">
            {prioritarias.map((brecha, indice) => (
              <li key={brecha.competencia_id}>
                <TarjetaBrecha brecha={brecha} posicion={indice + 1} tieneOferta={tieneOferta[String(brecha.competencia_id)] === true} />
              </li>
            ))}
          </ol>
        </section>
      )}

      {otras.length > 0 && (
        <section aria-labelledby="dnf-otras" className="flex flex-col gap-4">
          <h2 id="dnf-otras" className="text-xl font-bold">
            {text.otras}
          </h2>
          <ul className="grid gap-4 md:grid-cols-2">
            {otras.map((brecha) => (
              <li key={brecha.competencia_id}>
                <TarjetaBrecha brecha={brecha} tieneOferta={tieneOferta[String(brecha.competencia_id)] === true} />
              </li>
            ))}
          </ul>
        </section>
      )}

      {cumplen.length > 0 && (
        <details className="rounded-xl border border-border bg-surface p-5">
          <summary className="cursor-pointer text-base font-bold">{text.cumples(cumplen.length)}</summary>
          <ul className="mt-3 flex flex-col gap-1.5 text-sm">
            {cumplen.map((c) => (
              <li key={c.competencia_id}>
                {c.nombre} · {c.nivel_actual ?? dnfText.sinPrioridad}
              </li>
            ))}
          </ul>
        </details>
      )}
    </div>
  )
}
