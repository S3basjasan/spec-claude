import { Badge } from '../../../../shared/ui/Badge'
import { IconChevronDown } from '../../../../shared/ui/icons'
import type { EstadoPosicion, PosicionDetalle as Detalle, PosicionResumen } from '../api'
import { posicionesText } from '../text'
import { PosicionDetalle } from './PosicionDetalle'

interface PosicionFilaProps {
  posicion: PosicionResumen
  abierta: boolean
  onAlternar: () => void
  esAdmin: boolean
  onEditar: (id: number) => void
  onSuprimir: (detalle: Detalle) => void
}

/** Ocupada usa el estilo normal, Vacante el de advertencia y Suprimida el de peligro (design/DESIGN.md §4). */
const VARIANTE: Record<EstadoPosicion, 'default' | 'warning' | 'danger'> = {
  Ocupada: 'default',
  Vacante: 'warning',
  Suprimida: 'danger',
}

/** Fila desplegable: toda la fila es el botón (design/DESIGN.md §5 Listado, puntos 10 y 11). */
export function PosicionFila({ posicion, abierta, onAlternar, esAdmin, onEditar, onSuprimir }: PosicionFilaProps) {
  const panelId = `posicion-${posicion.id}`

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
          <span className="w-22.5 shrink-0 text-sm tabular-nums text-muted">{posicion.codigo}</span>
          <span className="min-w-0 flex-[1.2_1_180px] text-base font-semibold">{posicion.puesto.nombre}</span>
          <span className="min-w-0 flex-[1_1_150px] text-sm text-muted">{posicion.unidad.nombre}</span>
          <span className="min-w-0 flex-[1_1_150px] text-sm">
            {posicion.supervisor ? (
              <>
                {posicion.supervisor.codigo}
                <span className="block text-[13px] text-muted">{posicion.supervisor.nombre}</span>
              </>
            ) : (
              <>
                <span aria-hidden="true">{posicionesText.list.topAuthorityDash}</span>
                <span className="block text-[13px] text-muted">{posicionesText.list.topAuthority}</span>
              </>
            )}
          </span>
          <span className={`min-w-0 flex-[1_1_140px] text-sm ${posicion.ocupante ? 'font-medium' : 'italic text-muted'}`}>
            {posicion.ocupante ? (posicion.ocupante.nombre ?? posicionesText.detail.unknownUser) : posicionesText.list.noOccupant}
          </span>
          <span className="w-25 shrink-0">
            <Badge variant={VARIANTE[posicion.estado]}>{posicionesText.statuses[posicion.estado]}</Badge>
          </span>
          <span className="w-27.5 shrink-0 text-sm">{posicionesText.modalities[posicion.modalidad]}</span>
          <IconChevronDown size={20} className={`shrink-0 transition-transform ${abierta ? 'rotate-180' : ''}`} />
        </button>
      </h2>
      {abierta && (
        <PosicionDetalle
          posicionId={posicion.id}
          codigo={posicion.codigo}
          panelId={panelId}
          esAdmin={esAdmin}
          onEditar={onEditar}
          onSuprimir={onSuprimir}
        />
      )}
    </li>
  )
}
