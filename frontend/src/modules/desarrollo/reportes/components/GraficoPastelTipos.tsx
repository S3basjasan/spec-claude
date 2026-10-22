import type { Grafico, HorasTipo } from '../api'
import { colorDeTipo } from '../estilosEstado'
import { horas, numero } from '../formato'
import { reportesText } from '../text'

const t = reportesText.graficos.distribucion

/** Pastel con las horas por cada uno de los siete tipos de formación (incluido Micro-learning) y su tabla de datos. */
export function GraficoPastelTipos({ grafico }: { grafico: Grafico<HorasTipo> }) {
  const datos = grafico.datos
  const total = datos.reduce((suma, d) => suma + d.horas, 0)

  const tramos = datos
    .filter((d) => d.horas > 0)
    .reduce<{ texto: string[]; acumulado: number }>(
      (parcial, d) => {
        const hasta = parcial.acumulado + d.horas
        const tramo = `${colorDeTipo[d.tipo]} ${(parcial.acumulado / total) * 360}deg ${(hasta / total) * 360}deg`
        return { texto: [...parcial.texto, tramo], acumulado: hasta }
      },
      { texto: [], acumulado: 0 },
    ).texto

  return (
    <figure aria-labelledby="graf-tipos-titulo" className="m-0 flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
      <figcaption>
        <div id="graf-tipos-titulo" className="text-base font-bold">{t.titulo}</div>
        <div className="text-[13px] text-muted">{t.descripcion}</div>
      </figcaption>

      {!grafico.disponible ? (
        <p className="m-0 text-sm text-muted">{reportesText.graficos.noDisponible}</p>
      ) : total === 0 ? (
        <p className="m-0 text-sm text-muted">{t.vacio}</p>
      ) : (
        <div className="flex flex-wrap items-center gap-5">
          <div role="img" aria-label={reportesText.graficos.aria.pastel} className="h-36 w-36 shrink-0 rounded-full border border-border" style={{ background: `conic-gradient(${tramos.join(', ')})` }} />
          <table className="min-w-52 flex-1 text-[13px]">
            <thead>
              <tr className="text-left text-muted">
                <th scope="col" className="py-1 pr-2 font-semibold">
                  {t.tipo}
                </th>
                <th scope="col" className="py-1 pr-2 text-right font-semibold">
                  {t.horas}
                </th>
                <th scope="col" className="py-1 text-right font-semibold">
                  {t.pct}
                </th>
              </tr>
            </thead>
            <tbody>
              {datos.map((d) => (
                <tr key={d.tipo}>
                  <td className="py-1 pr-2">
                    <span className="flex items-center gap-2">
                      <span aria-hidden="true" className="inline-block h-2.5 w-2.5 rounded-sm" style={{ background: colorDeTipo[d.tipo] }} />
                      {reportesText.nombreDeTipo(d.tipo)}
                    </span>
                  </td>
                  <td className="py-1 pr-2 text-right tabular-nums">{horas(d.horas)}</td>
                  <td className="py-1 text-right tabular-nums">{numero((d.horas / total) * 100, 1)} %</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </figure>
  )
}
