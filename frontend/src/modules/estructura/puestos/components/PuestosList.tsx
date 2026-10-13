import { useState } from 'react'
import type { PuestoResumen } from '../api'
import { puestosText } from '../text'
import { PuestoFila } from './PuestoFila'

interface PuestosListProps {
  items: PuestoResumen[]
  esAdmin: boolean
}

/** Listado de puestos con filas desplegables: solo una permanece abierta a la vez. */
export function PuestosList({ items, esAdmin }: PuestosListProps) {
  const [abierta, setAbierta] = useState<number | null>(null)
  const text = puestosText.list

  return (
    <div className="overflow-hidden rounded-xl border border-border bg-surface">
      <div aria-hidden="true" className="hidden gap-4 border-b border-border px-5 py-2.5 text-[13px] font-semibold text-muted md:flex">
        <span className="w-22.5 shrink-0">{text.columns.code}</span>
        <span className="min-w-0 flex-[1_1_200px]">{text.columns.name}</span>
        <span className="min-w-0 flex-[1.3_1_240px]">{text.columns.description}</span>
        <span className="w-22.5 shrink-0">{text.columns.status}</span>
        <span className="w-5 shrink-0" />
      </div>
      <ul aria-label={text.listLabel} className="m-0 list-none p-0">
        {items.map((puesto) => (
          <PuestoFila
            key={puesto.id}
            puesto={puesto}
            abierta={abierta === puesto.id}
            onAlternar={() => setAbierta((actual) => (actual === puesto.id ? null : puesto.id))}
            esAdmin={esAdmin}
          />
        ))}
      </ul>
    </div>
  )
}
