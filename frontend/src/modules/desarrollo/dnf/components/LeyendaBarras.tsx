import { dnfText } from '../text'

const text = dnfText.colaborador.leyenda

/** «Cómo leer las barras»: qué significa cada barra y la escala A–D. */
export function LeyendaBarras() {
  return (
    <section aria-labelledby="dnf-leyenda" className="rounded-xl border border-border bg-surface p-5">
      <h2 id="dnf-leyenda" className="text-base font-bold">
        {text.titulo}
      </h2>
      <ul className="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm">
        <li className="flex items-center gap-2">
          <span aria-hidden="true" className="inline-block h-2.5 w-6 rounded-full bg-text" />
          {text.requerido}
        </li>
        <li className="flex items-center gap-2">
          <span aria-hidden="true" className="inline-block h-2.5 w-6 rounded-full bg-accent" />
          {text.actual}
        </li>
      </ul>
      <p className="mt-2 text-[13px] text-muted">{text.nota}</p>
    </section>
  )
}
