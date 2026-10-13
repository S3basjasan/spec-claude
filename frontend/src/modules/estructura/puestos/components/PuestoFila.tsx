import { Badge } from '../../../../shared/ui/Badge'
import { IconChevronDown } from '../../../../shared/ui/icons'
import type { PuestoResumen } from '../api'
import { puestosText } from '../text'
import { PuestoDetalle } from './PuestoDetalle'

interface PuestoFilaProps {
  puesto: PuestoResumen
  abierta: boolean
  onAlternar: () => void
  esAdmin: boolean
}

/** Fila desplegable: toda la fila es el botón (design/DESIGN.md §5 Listado, punto 10). */
export function PuestoFila({ puesto, abierta, onAlternar, esAdmin }: PuestoFilaProps) {
  const panelId = `puesto-${puesto.id}`

  return (
    <li className="border-t border-border first:border-t-0">
      <h2 className="m-0 text-base font-normal">
        <button
          type="button"
          aria-expanded={abierta}
          aria-controls={abierta ? panelId : undefined}
          onClick={onAlternar}
          className={`flex min-h-16 w-full cursor-pointer flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 text-left hover:bg-soft ${
            abierta ? 'bg-soft' : 'bg-transparent'
          }`}
        >
          <span className="w-22.5 shrink-0 text-sm tabular-nums text-muted">{puesto.codigo}</span>
          <span className="min-w-0 flex-[1_1_200px] text-base font-semibold">{puesto.nombre}</span>
          <span className="min-w-0 flex-[1.3_1_240px] text-sm leading-snug text-muted">{puesto.descripcion}</span>
          <span className="w-22.5 shrink-0">
            <Badge variant={puesto.estado === 'Activo' ? 'default' : 'neutral'}>{puestosText.statuses[puesto.estado]}</Badge>
          </span>
          <IconChevronDown size={20} className={`shrink-0 transition-transform ${abierta ? 'rotate-180' : ''}`} />
        </button>
      </h2>
      {abierta && <PuestoDetalle puestoId={puesto.id} nombre={puesto.nombre} panelId={panelId} esAdmin={esAdmin} />}
    </li>
  )
}
