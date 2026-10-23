import { Link } from 'react-router-dom'
import type { FilaDeMapa } from '../api'
import { adecuacionText, textoDePorcentajes } from '../text'
import { PreparacionBadge } from './Etiquetas'

const t = adecuacionText.mapa

/** Tabla del mapa de talento: una fila por colaborador y puesto objetivo, con su adecuación vigente. */
export function TablaDelMapa({ filas }: { filas: FilaDeMapa[] }) {
  return (
    <section aria-label={t.tabla} className="overflow-x-auto rounded-xl border border-border bg-surface">
      <table className="w-full min-w-[960px] border-collapse text-sm">
        <thead>
          <tr className="text-left text-[13px] text-muted">
            <th scope="col" className="py-2.5 pl-5 pr-3 font-semibold">{t.colaborador}</th>
            <th scope="col" className="px-3 py-2.5 font-semibold">{t.ruta}</th>
            <th scope="col" className="px-3 py-2.5 font-semibold">{t.puestos}</th>
            <th scope="col" className="px-3 py-2.5 font-semibold">{t.porcentajes}</th>
            <th scope="col" className="px-3 py-2.5 font-semibold">{t.preparacion}</th>
            <th scope="col" className="px-3 py-2.5 font-semibold">{t.brechas}</th>
            <th scope="col" className="px-3 py-2.5 font-semibold">{t.tiempoMaximo}</th>
            <th scope="col" className="px-3 py-2.5 font-semibold">{t.programa}</th>
            <th scope="col" className="py-2.5 pl-3 pr-5 font-semibold">{t.acciones}</th>
          </tr>
        </thead>
        <tbody>
          {filas.map((f) => (
            <tr key={f.adecuacion_id} className="border-t border-border align-top">
              <td className="py-3 pl-5 pr-3">
                <div className="font-semibold">{f.colaborador.nombre}</div>
                {f.colaborador.departamento !== null && <div className="text-xs text-muted">{f.colaborador.departamento}</div>}
              </td>
              <td className="px-3 py-3">{f.ruta?.nombre ?? t.sinRuta}</td>
              <td className="px-3 py-3">
                {f.puesto_actual?.nombre ?? t.sinPuestoActual} → {f.puesto_objetivo.nombre}
              </td>
              <td className="px-3 py-3 tabular-nums">{textoDePorcentajes(f.cumplimiento_por_dimension)}</td>
              <td className="px-3 py-3"><PreparacionBadge valor={f.preparacion} /></td>
              <td className="px-3 py-3 tabular-nums">{f.brechas}</td>
              <td className="px-3 py-3 tabular-nums">{f.tiempo_maximo_cierre_meses ?? '—'}</td>
              <td className="px-3 py-3 text-muted">—</td>
              <td className="py-3 pl-3 pr-5">
                <div className="flex flex-wrap gap-2">
                  <Link
                    to={`/adecuacion/evaluaciones/${f.adecuacion_id}`}
                    aria-label={t.diagnosticoAria(f.colaborador.nombre)}
                    className="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 font-semibold text-text no-underline hover:bg-soft"
                  >
                    {t.diagnostico}
                  </Link>
                  {f.brechas > 0 ? (
                    <Link
                      to={`/pdi/nuevo?colaborador=${f.colaborador.id}&tipo=Talento&puesto_objetivo=${f.puesto_objetivo.id}`}
                      aria-label={t.incluirAria(f.colaborador.nombre)}
                      className="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 font-semibold text-text no-underline hover:bg-soft"
                    >
                      {t.incluir}
                    </Link>
                  ) : (
                    <button
                      type="button"
                      disabled
                      aria-label={t.incluirAria(f.colaborador.nombre)}
                      title={t.incluirMotivo}
                      className="min-h-11 cursor-not-allowed rounded-lg border border-border bg-surface px-4 font-semibold text-muted opacity-60"
                    >
                      {t.incluir}
                    </button>
                  )}
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  )
}
