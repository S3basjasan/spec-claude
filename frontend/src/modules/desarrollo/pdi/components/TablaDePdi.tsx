import { Link } from 'react-router-dom'
import type { PdiResumen } from '../api'
import { rangoDeFechas } from '../fecha'
import { pdiText } from '../text'
import { BadgeDeEstadoDePdi, BadgeDeTipoDePdi, BarraDeAvance } from './Etiquetas'

const t = pdiText.lista

/** Tabla de planes (en pantallas angostas cada fila pasa a tarjeta): colaborador, tipo, estado, responsable, periodo, avance y hitos vencidos. */
export function TablaDePdi({ planes }: { planes: PdiResumen[] }) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface max-md:overflow-visible max-md:border-0 max-md:bg-transparent">
      <table className="w-full border-collapse text-left text-[14px] max-md:block">
        <caption className="sr-only">{pdiText.titulo}</caption>
        <thead className="bg-bg text-[13px] text-muted max-md:sr-only">
          <tr>
            {Object.values(t.columnas).map((columna) => (
              <th key={columna} scope="col" className="px-3 py-2 font-semibold">
                {columna}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="max-md:flex max-md:flex-col max-md:gap-3">
          {planes.map((p) => (
            <tr key={p.id} className="border-t border-border hover:bg-soft max-md:block max-md:rounded-xl max-md:border max-md:bg-surface max-md:p-3">
              <td className="px-3 py-2 max-md:block">
                <p className="m-0 font-semibold">{p.colaborador.nombre}</p>
                <p className="m-0 text-[13px] text-muted">
                  {p.codigo}
                  {p.colaborador.departamento !== null && ` · ${p.colaborador.departamento}`}
                </p>
              </td>
              <td className="px-3 py-2 max-md:block">
                <BadgeDeTipoDePdi valor={p.tipo} />
              </td>
              <td className="px-3 py-2 max-md:block">
                <BadgeDeEstadoDePdi valor={p.estado} />
              </td>
              <td className="px-3 py-2 max-md:block">{p.responsable.nombre}</td>
              <td className="whitespace-nowrap px-3 py-2 max-md:block">{rangoDeFechas(p.fecha_inicio, p.fecha_fin)}</td>
              <td className="px-3 py-2 max-md:block">
                <BarraDeAvance valor={p.avance} />
              </td>
              <td className="px-3 py-2 max-md:block">
                {p.hitos_vencidos > 0 ? (
                  <span className="rounded-full bg-dangersoft px-2.5 py-[3px] text-xs font-semibold text-danger">
                    {p.hitos_vencidos} · {pdiText.vencido}
                  </span>
                ) : (
                  <span className="text-muted">{pdiText.sinDato}</span>
                )}
              </td>
              <td className="px-3 py-2 max-md:block">
                <Link to={`/pdi/${p.id}`} className="font-semibold text-accent underline" aria-label={`${t.abrir} ${p.codigo}`}>
                  {t.abrir}
                </Link>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
