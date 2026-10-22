import { Link } from 'react-router-dom'
import type { Comparacion } from '../api'
import { adecuacionText, textoDePorcentajes } from '../text'
import { PreparacionBadge } from './Etiquetas'

const t = adecuacionText.comparar

/** «Ranking para {puesto}» con los Incompletos (sin puntaje) y los candidatos sin adecuación en bloques propios. */
export function TablaDeRanking({ comparacion }: { comparacion: Comparacion }) {
  const { ranking, incompletos, sin_adecuacion: sinAdecuacion } = comparacion

  return (
    <div className="flex flex-col gap-5">
      <section aria-labelledby="ranking-titulo" className="overflow-x-auto rounded-xl border border-border bg-surface">
        <h2 id="ranking-titulo" className="m-0 px-5 pb-2 pt-4 text-lg font-semibold">
          {t.rankingTitulo(comparacion.puesto_objetivo.nombre)}
        </h2>

        {ranking.length === 0 ? (
          <p className="m-0 px-5 pb-5 text-sm text-muted">{t.sinRanking}</p>
        ) : (
          <table className="w-full min-w-[720px] border-collapse text-sm">
            <thead>
              <tr className="text-left text-[13px] text-muted">
                <th scope="col" className="py-2.5 pl-5 pr-3 font-semibold">{t.posicion}</th>
                <th scope="col" className="px-3 py-2.5 font-semibold">{t.candidato}</th>
                <th scope="col" className="px-3 py-2.5 font-semibold">{t.porcentajes}</th>
                <th scope="col" className="px-3 py-2.5 font-semibold">{t.puntaje}</th>
                <th scope="col" className="px-3 py-2.5 font-semibold">{t.preparacion}</th>
                <th scope="col" className="py-2.5 pl-3 pr-5 font-semibold">{t.accion}</th>
              </tr>
            </thead>
            <tbody>
              {ranking.map((r) => (
                <tr key={r.adecuacion_id} className="border-t border-border">
                  <td className="py-3 pl-5 pr-3 font-semibold tabular-nums">{r.posicion}</td>
                  <td className="px-3 py-3">
                    <div className="font-semibold">{r.colaborador.nombre}</div>
                    <div className="text-xs text-muted">{[r.puesto_actual, t.brechas(r.brechas)].filter(Boolean).join(' · ')}</div>
                  </td>
                  <td className="px-3 py-3 tabular-nums">{textoDePorcentajes(r.cumplimiento_por_dimension)}</td>
                  <td className="px-3 py-3">
                    <div className="font-bold tabular-nums">{r.puntaje} %</div>
                    <div role="img" aria-label={`${r.puntaje} %`} className="mt-1 h-[7px] w-32 rounded bg-bg">
                      <div className="h-full rounded bg-info" style={{ width: `${Math.max(0, Math.min(100, r.puntaje))}%` }} />
                    </div>
                  </td>
                  <td className="px-3 py-3"><PreparacionBadge valor={r.preparacion} /></td>
                  <td className="py-3 pl-3 pr-5">
                    <Link
                      to={`/adecuacion/evaluaciones/${r.adecuacion_id}`}
                      aria-label={t.verDiagnosticoAria(r.colaborador.nombre)}
                      className="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 font-semibold text-text no-underline hover:bg-soft"
                    >
                      {t.verDiagnostico}
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>

      {incompletos.length > 0 && (
        <section aria-labelledby="incompletos-titulo" className="rounded-xl border border-border bg-surface px-5 py-4">
          <h2 id="incompletos-titulo" className="m-0 text-lg font-semibold">{t.incompletosTitulo}</h2>
          <p className="m-0 mt-0.5 text-[13px] text-muted">{t.incompletosAyuda}</p>
          <ul className="m-0 mt-3 flex list-none flex-wrap gap-2 p-0">
            {incompletos.map((i) => (
              <li key={i.adecuacion_id} className="flex items-center gap-2 rounded-full border border-border px-3 py-1.5 text-sm">
                {i.colaborador.nombre}
                <Link to={`/adecuacion/evaluaciones/${i.adecuacion_id}`} aria-label={t.verDiagnosticoAria(i.colaborador.nombre)} className="font-semibold text-accent">
                  {t.verDiagnostico}
                </Link>
              </li>
            ))}
          </ul>
        </section>
      )}

      {sinAdecuacion.length > 0 && (
        <section aria-labelledby="sin-adecuacion-titulo" className="rounded-xl border border-border bg-surface px-5 py-4">
          <h2 id="sin-adecuacion-titulo" className="m-0 text-lg font-semibold">{t.sinAdecuacionTitulo}</h2>
          <ul className="m-0 mt-3 flex list-none flex-col gap-2 p-0">
            {sinAdecuacion.map((s) => (
              <li key={s.colaborador.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span>{s.colaborador.nombre}</span>
                <Link to="/adecuacion" className="font-semibold text-accent">{t.generar}</Link>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  )
}
