import type { FilaAccion } from '../api'
import { planText } from '../text'
import { BadgeEstadoPlan } from './BadgeEstadoPlan'
import { ChipTipo } from './ChipTipo'

const c = planText.acciones.columnas

interface ListaAccionesProps {
  acciones: FilaAccion[]
  onSeleccionar: (accion: FilaAccion) => void
}

/** Tabla de acciones: la columna Estado muestra el estado del plan y las canceladas llevan además el badge «Cancelada». */
export function ListaAcciones({ acciones, onSeleccionar }: ListaAccionesProps) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface">
      <table aria-label={planText.acciones.tableLabel} className="w-full min-w-250 border-collapse text-left">
        <thead>
          <tr className="text-[13px] font-semibold text-muted">
            <th scope="col" className="px-4 py-3.5">{c.plan}</th>
            <th scope="col" className="px-4 py-3.5">{c.nombre}</th>
            <th scope="col" className="px-4 py-3.5">{c.competencia}</th>
            <th scope="col" className="px-4 py-3.5">{c.tipo}</th>
            <th scope="col" className="px-4 py-3.5">{c.fechas}</th>
            <th scope="col" className="px-4 py-3.5">{c.formador}</th>
            <th scope="col" className="px-4 py-3.5">{c.cupo}</th>
            <th scope="col" className="px-4 py-3.5">{c.costo}</th>
            <th scope="col" className="px-4 py-3.5">{c.estado}</th>
          </tr>
        </thead>
        <tbody>
          {acciones.map((accion) => (
            <tr key={accion.id} className="border-t border-border text-[15px] hover:bg-soft">
              <td className="px-4 py-3.5 font-semibold">{accion.plan.codigo}</td>
              <td className="px-4 py-3.5">
                <button type="button" onClick={() => onSeleccionar(accion)} className="min-h-11 text-left font-semibold text-accent underline">
                  {accion.nombre}
                </button>
              </td>
              <td className="px-4 py-3.5">{accion.competencias.map((x) => x.nombre).join(', ') || planText.sinDato}</td>
              <td className="px-4 py-3.5">
                <ChipTipo tipo={accion.tipo} />
              </td>
              <td className="px-4 py-3.5">{planText.rango(accion.fecha_inicio, accion.fecha_fin)}</td>
              <td className="px-4 py-3.5">{accion.formador ?? planText.sinDato}</td>
              <td className="px-4 py-3.5">{planText.cupo(accion.inscritos, accion.capacidad_maxima)}</td>
              <td className="px-4 py-3.5">{planText.dinero(accion.costo)}</td>
              <td className="px-4 py-3.5">
                <div className="flex flex-wrap gap-1.5">
                  <BadgeEstadoPlan estado={accion.plan.estado} />
                  {accion.cancelada && <span className="inline-block rounded-full bg-dangersoft px-2.5 py-[3px] text-[13px] font-bold text-danger">{planText.estadoAccion.Cancelada}</span>}
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
