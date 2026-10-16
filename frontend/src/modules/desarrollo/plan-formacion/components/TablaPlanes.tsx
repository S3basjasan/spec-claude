import type { FilaPlan } from '../api'
import { planText } from '../text'
import { AccionesPlan } from './AccionesPlan'
import { BadgeEstadoPlan } from './BadgeEstadoPlan'

const c = planText.planes.columnas

/** Tabla de planes (patrón Listado): código, nombre, año, estado, acciones, costo total, aprobador y botones. */
export function TablaPlanes({ planes }: { planes: FilaPlan[] }) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface">
      <table aria-label={planText.planes.tableLabel} className="w-full min-w-200 border-collapse text-left">
        <thead>
          <tr className="text-[13px] font-semibold text-muted">
            <th scope="col" className="px-4 py-3.5">{c.codigo}</th>
            <th scope="col" className="px-4 py-3.5">{c.nombre}</th>
            <th scope="col" className="px-4 py-3.5">{c.anio}</th>
            <th scope="col" className="px-4 py-3.5">{c.estado}</th>
            <th scope="col" className="px-4 py-3.5">{c.acciones}</th>
            <th scope="col" className="px-4 py-3.5">{c.costo}</th>
            <th scope="col" className="px-4 py-3.5">{c.aprobador}</th>
            <th scope="col" className="px-4 py-3.5 text-right">{c.botones}</th>
          </tr>
        </thead>
        <tbody>
          {planes.map((plan) => (
            <tr key={plan.id} className="border-t border-border text-[15px] hover:bg-soft">
              <td className="px-4 py-3.5 font-semibold">{plan.codigo}</td>
              <td className="px-4 py-3.5">{plan.nombre}</td>
              <td className="px-4 py-3.5">{plan.anio}</td>
              <td className="px-4 py-3.5">
                <BadgeEstadoPlan estado={plan.estado} />
              </td>
              <td className="px-4 py-3.5">{plan.cantidad_acciones}</td>
              <td className="px-4 py-3.5">{planText.dinero(plan.costo_total)}</td>
              <td className="px-4 py-3.5">{plan.aprobador?.nombre ?? planText.planes.sinAprobador}</td>
              <td className="px-4 py-3.5">
                <AccionesPlan planId={plan.id} nombre={plan.nombre} puede={plan.puede} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
