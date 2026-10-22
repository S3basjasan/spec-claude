import type { Grafico, PuntoBrechas } from '../api'
import { mes, mesLargo } from '../formato'
import { reportesText } from '../text'

const t = reportesText.graficos.brechas

const ANCHO = 640
const ALTO = 220
const MARGEN = { izq: 34, der: 12, sup: 12, inf: 26 }

/** Línea mensual de brechas activas al cierre del mes y brechas cerradas en el mes, con una tabla accesible equivalente. */
export function GraficoLineaBrechas({ grafico }: { grafico: Grafico<PuntoBrechas> }) {
  const datos = grafico.datos
  const hayDatos = datos.some((p) => p.activas > 0 || p.cerradas > 0)
  const maximo = Math.max(1, ...datos.map((p) => Math.max(p.activas, p.cerradas)))
  const ancho = ANCHO - MARGEN.izq - MARGEN.der
  const alto = ALTO - MARGEN.sup - MARGEN.inf
  const x = (i: number) => MARGEN.izq + (datos.length <= 1 ? ancho / 2 : (i / (datos.length - 1)) * ancho)
  const y = (v: number) => MARGEN.sup + alto - (v / maximo) * alto
  const puntos = (campo: 'activas' | 'cerradas') => datos.map((p, i) => `${x(i)},${y(p[campo])}`).join(' ')
  const marcas = [...new Set([0, 0.25, 0.5, 0.75, 1].map((f) => Math.round(maximo * f)))]

  return (
    <figure aria-labelledby="graf-brechas-titulo" className="m-0 flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
      <figcaption className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <div id="graf-brechas-titulo" className="text-base font-bold">{t.titulo}</div>
          <div className="text-[13px] text-muted">{t.descripcion}</div>
        </div>
        <ul aria-label="Leyenda" className="m-0 flex list-none gap-4 p-0 text-[13px]">
          <li className="flex items-center gap-1.5">
            <span aria-hidden="true" className="inline-block h-0.5 w-4" style={{ background: 'var(--info)' }} />
            {t.activas}
          </li>
          <li className="flex items-center gap-1.5">
            <span aria-hidden="true" className="inline-block h-0.5 w-4" style={{ background: 'var(--accent)' }} />
            {t.cerradas}
          </li>
        </ul>
      </figcaption>

      {!grafico.disponible ? (
        <p className="m-0 text-sm text-muted">{reportesText.graficos.noDisponible}</p>
      ) : datos.length === 0 || !hayDatos ? (
        <p className="m-0 text-sm text-muted">{t.vacio}</p>
      ) : (
        <>
          <svg role="img" aria-label={reportesText.graficos.aria.linea(datos.length)} viewBox={`0 0 ${ANCHO} ${ALTO}`} className="h-auto w-full">
            {marcas.map((m) => (
              <g key={m}>
                <line x1={MARGEN.izq} x2={ANCHO - MARGEN.der} y1={y(m)} y2={y(m)} stroke="var(--border)" strokeWidth="1" />
                <text x={MARGEN.izq - 6} y={y(m) + 4} textAnchor="end" fontSize="11" fill="var(--muted)">
                  {m}
                </text>
              </g>
            ))}
            <polyline points={puntos('activas')} fill="none" stroke="var(--info)" strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round" />
            <polyline points={puntos('cerradas')} fill="none" stroke="var(--accent)" strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round" />
            {datos.map((p, i) => (
              <g key={p.mes}>
                <circle cx={x(i)} cy={y(p.activas)} r="3.5" fill="var(--info)" />
                <circle cx={x(i)} cy={y(p.cerradas)} r="3.5" fill="var(--accent)" />
                {(datos.length <= 12 || i % 2 === 0) && (
                  <text x={x(i)} y={ALTO - 8} textAnchor="middle" fontSize="11" fill="var(--muted)">
                    {mes(p.mes).split(' ')[0]}
                  </text>
                )}
              </g>
            ))}
          </svg>
          <div className="sr-only">
<table>
            <caption>{t.titulo}</caption>
            <thead>
              <tr>
                <th scope="col">{t.mes}</th>
                <th scope="col">{t.activas}</th>
                <th scope="col">{t.cerradas}</th>
              </tr>
            </thead>
            <tbody>
              {datos.map((p) => (
                <tr key={p.mes}>
                  <th scope="row">{mesLargo(p.mes)}</th>
                  <td>{p.activas}</td>
                  <td>{p.cerradas}</td>
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
