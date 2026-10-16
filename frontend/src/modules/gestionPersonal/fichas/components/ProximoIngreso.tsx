import type { ProximoIngreso as ProximoIngresoDatos } from '../api'
import { fichasText } from '../text'

const text = fichasText.entry

/** Bloque «Ingreso programado» del detalle: fecha, contrato, puesto y unidad del próximo vínculo. */
export function ProximoIngreso({ ingreso }: { ingreso: ProximoIngresoDatos }) {
  const posicion = [ingreso.posicion?.puesto, ingreso.posicion?.unidad].filter(Boolean).join(' · ')

  return (
    <div className="mt-4 rounded-lg border border-border bg-soft px-3.5 py-3 text-sm">
      <p className="flex flex-wrap items-center gap-2 font-semibold">
        {text.nextEntry}
        <span className="rounded-full bg-border px-2.5 py-[3px] text-[13px] font-semibold text-muted">
          {fichasText.detail.trajectoryScheduled}
        </span>
      </p>
      <p className="mt-1 text-muted">{text.nextEntryDetail(ingreso.fecha_ingreso, ingreso.tipo_contrato.etiqueta)}</p>
      {posicion !== '' && <p className="mt-0.5">{posicion}</p>}
    </div>
  )
}
