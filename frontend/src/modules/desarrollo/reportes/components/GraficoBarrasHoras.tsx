import type { Grafico, HorasDepartamento } from '../api'
import { horas } from '../formato'
import { reportesText } from '../text'

const t = reportesText.graficos.horasDepartamento

/** Barras horizontales de horas-persona de actividades y de micro-learning por departamento actual del colaborador. */
export function GraficoBarrasHoras({ grafico }: { grafico: Grafico<HorasDepartamento> }) {
  const datos = grafico.datos
  const maximo = Math.max(1, ...datos.map((d) => d.horas_actividades + d.horas_microlearning))

  return (
    <figure aria-labelledby="graf-horas-titulo" className="m-0 flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
      <figcaption>
        <div id="graf-horas-titulo" className="text-base font-bold">{t.titulo}</div>
        <div className="text-[13px] text-muted">{t.descripcion}</div>
      </figcaption>

      {!grafico.disponible ? (
        <p className="m-0 text-sm text-muted">{reportesText.graficos.noDisponible}</p>
      ) : datos.length === 0 ? (
        <p className="m-0 text-sm text-muted">{t.vacio}</p>
      ) : (
        <>
          <ul role="img" aria-label={reportesText.graficos.aria.barras(datos.length)} className="m-0 flex list-none flex-col gap-2.5 p-0">
            {datos.map((d) => {
              const total = d.horas_actividades + d.horas_microlearning
              return (
                <li key={d.departamento_id ?? 'sin'} className="grid grid-cols-[minmax(80px,140px)_1fr_auto] items-center gap-2.5 text-[13px]">
                  <span className="truncate">{d.departamento}</span>
                  <span className="flex h-3.5 overflow-hidden rounded bg-neutralsoft" aria-hidden="true">
                    <span style={{ width: `${(d.horas_actividades / maximo) * 100}%`, background: 'var(--accent)' }} />
                    <span style={{ width: `${(d.horas_microlearning / maximo) * 100}%`, background: 'var(--info)' }} />
                  </span>
                  <span className="font-semibold tabular-nums">{horas(total)}</span>
                </li>
              )
            })}
          </ul>
          <ul aria-label="Leyenda" className="m-0 flex list-none gap-4 p-0 text-[13px]">
            <li className="flex items-center gap-1.5">
              <span aria-hidden="true" className="inline-block h-2.5 w-2.5 rounded-sm" style={{ background: 'var(--accent)' }} />
              {t.actividades}
            </li>
            <li className="flex items-center gap-1.5">
              <span aria-hidden="true" className="inline-block h-2.5 w-2.5 rounded-sm" style={{ background: 'var(--info)' }} />
              {t.micro}
            </li>
          </ul>
          <div className="sr-only">
<table>
            <caption>{t.titulo}</caption>
            <thead>
              <tr>
                <th scope="col">{reportesText.filtros.departamento}</th>
                <th scope="col">{t.actividades}</th>
                <th scope="col">{t.micro}</th>
              </tr>
            </thead>
            <tbody>
              {datos.map((d) => (
                <tr key={d.departamento_id ?? 'sin'}>
                  <th scope="row">{d.departamento}</th>
                  <td>{horas(d.horas_actividades)}</td>
                  <td>{horas(d.horas_microlearning)}</td>
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
