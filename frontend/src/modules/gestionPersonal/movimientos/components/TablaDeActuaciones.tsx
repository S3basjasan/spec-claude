import { Link } from 'react-router-dom'
import type { FilaActuacion } from '../api'
import { movimientosText } from '../text'
import { EstadoDeActuacion } from './EstadoDeActuacion'

const text = movimientosText.list

function momentoLegible(iso: string): string {
  const valor = new Date(iso)
  return Number.isNaN(valor.getTime()) ? iso : valor.toLocaleDateString('es-EC', { dateStyle: 'medium' })
}

function periodoOFecha(fila: FilaActuacion): string {
  if (fila.periodo) return fila.periodo.inicio === fila.periodo.fin ? fila.periodo.inicio : `${fila.periodo.inicio} → ${fila.periodo.fin}`
  if (fila.fecha_efectiva) return text.effective(fila.fecha_efectiva)
  return movimientosText.none
}

/** Tabla del listado del Administrador: tipo, colaborador, presentación, responsable, período o fecha efectiva y estado. */
export function TablaDeActuaciones({ filas }: { filas: FilaActuacion[] }) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface">
      <table aria-label={text.tableLabel} className="w-full min-w-[760px] border-collapse text-left text-sm">
        <thead className="bg-soft text-[13px] text-muted">
          <tr>
            <th scope="col" className="px-4 py-3 font-semibold">{text.columns.type}</th>
            <th scope="col" className="px-4 py-3 font-semibold">{text.columns.person}</th>
            <th scope="col" className="px-4 py-3 font-semibold">{text.columns.presented}</th>
            <th scope="col" className="px-4 py-3 font-semibold">{text.columns.by}</th>
            <th scope="col" className="px-4 py-3 font-semibold">{text.columns.when}</th>
            <th scope="col" className="px-4 py-3 font-semibold">{text.columns.status}</th>
          </tr>
        </thead>
        <tbody>
          {filas.map((fila) => (
            <tr key={fila.id} className="border-t border-border">
              <td className="px-4 py-3">
                <Link
                  to={`/movimientos-personal/${fila.id}`}
                  aria-label={text.openRequest(movimientosText.tipos[fila.tipo], fila.colaborador.nombre)}
                  className="font-semibold text-accent underline"
                >
                  {movimientosText.tipos[fila.tipo]}
                </Link>
              </td>
              <td className="px-4 py-3">
                <span className="font-medium">{fila.colaborador.nombre}</span>
                <span className="block text-[13px] text-muted">{fila.colaborador.codigo}</span>
              </td>
              <td className="px-4 py-3">{momentoLegible(fila.presentada_en)}</td>
              <td className="px-4 py-3">{fila.en_nombre_de_colaborador ? text.onBehalf(fila.presentada_por.nombre) : (fila.presentada_por.nombre ?? movimientosText.none)}</td>
              <td className="px-4 py-3">{periodoOFecha(fila)}</td>
              <td className="px-4 py-3">
                <EstadoDeActuacion estado={fila.estado} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
