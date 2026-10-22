import type { ProximaAccion } from '../api'
import { fecha } from '../formato'
import { reportesText } from '../text'

const t = reportesText.colaborador.plan

/** «Mi plan de desarrollo»: las próximas acciones formativas del colaborador, en orden de fecha. */
export function PlanDeDesarrollo({ acciones }: { acciones: ProximaAccion[] }) {
  return (
    <section aria-labelledby="mp-plan-titulo" className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
      <div>
        <h2 id="mp-plan-titulo" className="m-0 text-base font-bold">
          {t.titulo}
        </h2>
        <p className="m-0 text-[13px] text-muted">{t.descripcion}</p>
      </div>
      {acciones.length === 0 ? (
        <p className="m-0 text-sm text-muted">{t.vacio}</p>
      ) : (
        <ol className="m-0 flex list-none flex-col gap-2 p-0">
          {acciones.map((a) => (
            <li key={a.accion_id} className="rounded-lg border border-border px-3 py-2.5">
              <div className="flex items-center justify-between gap-2 text-xs text-muted">
                <span className="font-semibold uppercase tracking-wide">{reportesText.nombreDeTipo(a.tipo)}</span>
                <span>{a.fecha_inicio === a.fecha_fin || a.fecha_inicio === null ? fecha(a.fecha_fin) : `${fecha(a.fecha_inicio)} – ${fecha(a.fecha_fin)}`}</span>
              </div>
              <div className="text-[15px] font-semibold">{a.nombre}</div>
              <div className="text-[13px] text-muted">{a.competencias.length === 0 ? t.sinCompetencias : t.cierraBrecha(a.competencias.join(', '))}</div>
            </li>
          ))}
        </ol>
      )}
    </section>
  )
}
