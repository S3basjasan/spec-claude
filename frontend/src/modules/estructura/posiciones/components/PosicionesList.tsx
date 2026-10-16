import { useState } from 'react'
import type { PosicionDetalle, PosicionResumen } from '../api'
import { posicionesText } from '../text'
import { PosicionFila } from './PosicionFila'

interface PosicionesListProps {
  items: PosicionResumen[]
  esAdmin: boolean
  onEditar: (id: number) => void
  onSuprimir: (detalle: PosicionDetalle) => void
}

/** Listado de posiciones con filas desplegables: solo una permanece abierta a la vez. */
export function PosicionesList({ items, esAdmin, onEditar, onSuprimir }: PosicionesListProps) {
  const [abierta, setAbierta] = useState<number | null>(null)
  const text = posicionesText.list

  return (
    <div className="overflow-hidden rounded-xl border border-border bg-surface">
      <div aria-hidden="true" className="hidden gap-4 border-b border-border px-5 py-2.5 text-[13px] font-semibold text-muted md:flex">
        <span className="w-22.5 shrink-0">{text.columns.code}</span>
        <span className="min-w-0 flex-[1.2_1_180px]">{text.columns.position}</span>
        <span className="min-w-0 flex-[1_1_150px]">{text.columns.unit}</span>
        <span className="min-w-0 flex-[1_1_150px]">{text.columns.reportsTo}</span>
        <span className="min-w-0 flex-[1_1_140px]">{text.columns.occupant}</span>
        <span className="w-25 shrink-0">{text.columns.status}</span>
        <span className="w-27.5 shrink-0">{text.columns.modality}</span>
        <span className="w-5 shrink-0" />
      </div>
      <ul aria-label={text.listLabel} className="m-0 list-none p-0">
        {items.map((posicion) => (
          <PosicionFila
            key={posicion.id}
            posicion={posicion}
            abierta={abierta === posicion.id}
            onAlternar={() => setAbierta((actual) => (actual === posicion.id ? null : posicion.id))}
            esAdmin={esAdmin}
            onEditar={onEditar}
            onSuprimir={onSuprimir}
          />
        ))}
      </ul>
    </div>
  )
}
