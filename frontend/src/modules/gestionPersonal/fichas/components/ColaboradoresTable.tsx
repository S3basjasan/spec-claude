import { Link } from 'react-router-dom'
import { Badge } from '../../../../shared/ui/Badge'
import type { ColaboradorResumen } from '../api'
import { fichasText } from '../text'

const text = fichasText.list

function Valor({ valor, disponible }: { valor: string | null; disponible: boolean }) {
  if (!disponible) return <span className="text-muted">{fichasText.unavailable}</span>
  if (valor === null) return <span className="text-muted">{fichasText.notRegistered}</span>
  return <>{valor}</>
}

/** Tabla del listado (patrón Listado de design/DESIGN.md §5). Con scroll interno bajo 640 px. */
export function ColaboradoresTable({ items }: { items: ColaboradorResumen[] }) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface">
      <table className="w-full min-w-170 border-collapse text-left text-[15px]">
        <caption className="sr-only">{text.listLabel}</caption>
        <thead>
          <tr className="text-[13px] text-muted">
            <th scope="col" className="px-4 py-3 font-semibold">
              {text.columns.code}
            </th>
            <th scope="col" className="px-4 py-3 font-semibold">
              {text.columns.collaborator}
            </th>
            <th scope="col" className="px-4 py-3 font-semibold">
              {text.columns.position}
            </th>
            <th scope="col" className="px-4 py-3 font-semibold">
              {text.columns.unit}
            </th>
            <th scope="col" className="px-4 py-3 font-semibold">
              {text.columns.status}
            </th>
            <th scope="col" className="px-4 py-3 font-semibold">
              {text.columns.actions}
            </th>
          </tr>
        </thead>
        <tbody>
          {items.map((fila) => {
            const nombre = `${fila.nombres} ${fila.apellidos}`
            return (
              <tr key={fila.id} className="border-t border-border">
                <td className="px-4 py-3 font-semibold">{fila.codigo}</td>
                <th scope="row" className="px-4 py-3 font-medium">
                  {nombre}
                </th>
                <td className="px-4 py-3">
                  <Valor valor={fila.puesto} disponible={fila.laboral_disponible} />
                </td>
                <td className="px-4 py-3">
                  <Valor valor={fila.unidad} disponible={fila.laboral_disponible} />
                </td>
                <td className="px-4 py-3">
                  {fila.laboral_disponible ? (
                    <Badge variant="neutral">{fichasText.statuses[fila.estado] ?? fila.estado}</Badge>
                  ) : (
                    <span className="text-muted">{fichasText.unavailable}</span>
                  )}
                </td>
                <td className="px-4 py-3">
                  <Link
                    to={`/colaboradores/${fila.id}`}
                    aria-label={text.viewRecordOf(nombre)}
                    className="font-semibold text-accent underline"
                  >
                    {text.viewRecord}
                  </Link>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
