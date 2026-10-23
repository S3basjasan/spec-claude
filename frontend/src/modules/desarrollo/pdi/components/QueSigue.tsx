import { Link } from 'react-router-dom'
import type { ItemQueSigue } from '../api'
import { fechaCorta } from '../fecha'
import { pdiText } from '../text'
import { BadgeDeTipoDePdi } from './Etiquetas'

const t = pdiText.mi

/** «Qué sigue»: próximas acciones e hitos con fecha (los vencidos se destacan); las acciones vinculadas enlazan a su actividad. */
export function QueSigue({ items }: { items: ItemQueSigue[] }) {
  return (
    <section aria-label={t.queSigue} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
      <h2 className="m-0 text-lg font-bold">{t.queSigue}</h2>
      {items.length === 0 ? (
        <p className="m-0 text-muted">{t.queSigueVacio}</p>
      ) : (
        <ul className="m-0 flex list-none flex-col gap-2 p-0">
          {items.map((i, n) => (
            <li key={`${i.plan_id}-${i.tipo}-${n}`} className={`flex flex-wrap items-center gap-2 rounded-lg border p-3 ${i.vencido ? 'border-danger' : 'border-border'}`}>
              <span className={`min-w-24 text-[13px] font-semibold ${i.vencido ? 'text-danger' : 'text-muted'}`}>{fechaCorta(i.fecha)}</span>
              <span className="font-semibold">{i.descripcion}</span>
              <span className="text-[13px] text-muted">{i.tipo === 'hito' ? pdiText.gantt.hito : t.accion}</span>
              <BadgeDeTipoDePdi valor={i.tipo_plan} />
              {i.vencido && <span className="rounded-full bg-dangersoft px-2.5 py-[3px] text-xs font-semibold text-danger">{pdiText.vencido}</span>}
              {i.actividad_id !== null && (
                <Link to="/actividades" className="font-semibold text-accent underline">
                  {pdiText.detalle.verActividad}
                </Link>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
