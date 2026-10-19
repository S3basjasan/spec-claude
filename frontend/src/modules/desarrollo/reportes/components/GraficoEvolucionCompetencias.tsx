import type { EvolucionCompetencia, NivelCompetencia } from '../api'
import { reportesText } from '../text'

const t = reportesText.colaborador.evolucion

/** A es el nivel más alto: D = 1 de 4 y A = 4 de 4. */
const ORDEN: Record<NivelCompetencia, number> = { D: 1, C: 2, B: 3, A: 4 }
const porcentaje = (nivel: NivelCompetencia) => (ORDEN[nivel] / 4) * 100

/** Barras de nivel en la primera evaluación frente a la más reciente, con la marca del nivel requerido y su texto equivalente. */
export function GraficoEvolucionCompetencias({ filas }: { filas: EvolucionCompetencia[] }) {
  return (
    <figure aria-labelledby="mp-evolucion-titulo" className="m-0 flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
      <figcaption className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h2 id="mp-evolucion-titulo" className="m-0 text-base font-bold">{t.titulo}</h2>
          <p className="m-0 text-[13px] text-muted">{t.descripcion}</p>
        </div>
        <ul aria-label="Leyenda" className="m-0 flex list-none flex-wrap gap-4 p-0 text-[13px]">
          <li className="flex items-center gap-1.5">
            <span aria-hidden="true" className="inline-block h-2.5 w-2.5 rounded-sm" style={{ background: 'var(--neutral)' }} />
            {t.primera}
          </li>
          <li className="flex items-center gap-1.5">
            <span aria-hidden="true" className="inline-block h-2.5 w-2.5 rounded-sm" style={{ background: 'var(--accent)' }} />
            {t.actual}
          </li>
          <li className="flex items-center gap-1.5">
            <span aria-hidden="true" className="inline-block h-3.5 w-0.5" style={{ background: 'var(--text)' }} />
            {t.requerido}
          </li>
        </ul>
      </figcaption>

      {filas.length === 0 ? (
        <p className="m-0 text-sm text-muted">{t.vacio}</p>
      ) : (
        <>
          <ul role="img" aria-label={t.aria(filas.length)} className="m-0 flex list-none flex-col gap-3 p-0">
            {filas.map((f) => (
              <li key={f.competencia_id} className="grid grid-cols-1 gap-1 sm:grid-cols-[minmax(120px,200px)_1fr_auto] sm:items-center sm:gap-3">
                <span className="truncate text-[13px] font-semibold">{f.competencia}</span>
                <span className="flex flex-col gap-1" aria-hidden="true">
                  <span className="relative block h-2.5 rounded bg-neutralsoft">
                    {f.nivel_primera !== null && <span className="absolute inset-y-0 left-0 rounded" style={{ width: `${porcentaje(f.nivel_primera)}%`, background: 'var(--neutral)' }} />}
                    <span className="absolute -inset-y-0.5 w-0.5" style={{ left: `${porcentaje(f.nivel_requerido)}%`, background: 'var(--text)' }} />
                  </span>
                  <span className="block h-2.5 rounded bg-neutralsoft">
                    <span className="block h-full rounded" style={{ width: `${porcentaje(f.nivel_actual)}%`, background: 'var(--accent)' }} />
                  </span>
                </span>
                <span className="text-[13px] tabular-nums text-muted">{t.texto(f.nivel_primera, f.nivel_actual, f.nivel_requerido)}</span>
              </li>
            ))}
          </ul>
          <div className="sr-only">
<table>
            <caption>{t.titulo}</caption>
            <thead>
              <tr>
                <th scope="col">Competencia</th>
                <th scope="col">{t.primera}</th>
                <th scope="col">{t.actual}</th>
                <th scope="col">{t.requerido}</th>
              </tr>
            </thead>
            <tbody>
              {filas.map((f) => (
                <tr key={f.competencia_id}>
                  <th scope="row">{f.competencia}</th>
                  <td>{f.solo_actual ? t.soloActual : reportesText.nivel(f.nivel_primera as NivelCompetencia)}</td>
                  <td>{reportesText.nivel(f.nivel_actual)}</td>
                  <td>{reportesText.nivel(f.nivel_requerido)}</td>
                </tr>
              ))}
            </tbody>
          </table>
</div>
        </>
      )}
    </figure>
  )
}
