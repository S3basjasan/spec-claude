import type { HistorialDeColaboradorItem } from '../api'
import { adecuacionText, textoDePorcentajes } from '../text'
import { PreparacionBadge } from './Etiquetas'

const t = adecuacionText.historial

/** Historial inmutable del colaborador elegido (las últimas evaluaciones). */
export function HistorialDeColaborador({ colaborador, items }: { colaborador: string; items: HistorialDeColaboradorItem[] }) {
  return (
    <section aria-labelledby="historial-titulo" className="overflow-x-auto rounded-xl border border-border bg-surface">
      <div className="px-5 pb-2 pt-4">
        <h2 id="historial-titulo" className="m-0 text-lg font-semibold">
          {t.titulo(colaborador)}
        </h2>
        <p className="m-0 mt-0.5 text-[13px] text-muted">{t.inmutable}</p>
      </div>

      {items.length === 0 ? (
        <p className="m-0 px-5 pb-5 text-sm text-muted">{t.vacio}</p>
      ) : (
        <table className="w-full min-w-[560px] border-collapse text-sm">
          <thead>
            <tr className="text-left text-[13px] text-muted">
              <th scope="col" className="py-2.5 pl-5 pr-3 font-semibold">
                {t.fecha}
              </th>
              <th scope="col" className="px-3 py-2.5 font-semibold">
                {t.puestoObjetivo}
              </th>
              <th scope="col" className="px-3 py-2.5 font-semibold">
                {t.porcentajes}
              </th>
              <th scope="col" className="py-2.5 pl-3 pr-5 font-semibold">
                {t.preparacion}
              </th>
            </tr>
          </thead>
          <tbody>
            {items.map((h) => (
              <tr key={h.id} className="border-t border-border">
                <td className="py-3 pl-5 pr-3">{h.fecha ?? t.sinDato}</td>
                <td className="px-3 py-3">{h.puesto_objetivo}</td>
                <td className="px-3 py-3 tabular-nums">{textoDePorcentajes(h.cumplimiento_por_dimension)}</td>
                <td className="py-3 pl-3 pr-5">
                  <PreparacionBadge valor={h.preparacion} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  )
}
