import { Badge } from '../../../../shared/ui/Badge'
import type { PosicionDelOrganigrama } from '../api'
import { organigramaText } from '../text'

/** `coincide` y `atenuada` solo se usan mientras hay búsqueda o filtro de unidad. */
export type ResaltadoDeTarjeta = 'normal' | 'coincide' | 'atenuada'

interface TarjetaDePosicionProps {
  posicion: PosicionDelOrganigrama
  seleccionada: boolean
  resaltado: ResaltadoDeTarjeta
  onSeleccionar: () => void
}

/**
 * Tarjeta de una posición: un botón con unidad, código, puesto, ocupante y estado (con texto). La Vacante se reconoce
 * además por el borde discontinuo y el fondo (design/DESIGN.md, patrón Organigrama).
 */
export function TarjetaDePosicion({ posicion, seleccionada, resaltado, onSeleccionar }: TarjetaDePosicionProps) {
  const vacante = posicion.estado === 'Vacante'
  const anillo = seleccionada ? 'ring-2 ring-accent' : resaltado === 'coincide' ? 'ring-2 ring-warning' : ''

  return (
    <button
      type="button"
      data-posicion-id={posicion.id}
      aria-pressed={seleccionada}
      onClick={onSeleccionar}
      className={`flex w-47 cursor-pointer flex-col gap-0.5 rounded-[10px] border-[1.5px] px-3 py-2.5 text-left text-text transition-opacity hover:shadow-md ${
        vacante ? 'border-dashed border-warning bg-warningsoft' : 'border-border bg-surface'
      } ${anillo} ${resaltado === 'atenuada' ? 'opacity-35' : ''}`}
    >
      <span className="text-[11px] font-semibold uppercase leading-snug tracking-wide text-accent">{posicion.unidad.nombre}</span>
      <span className="mt-1 text-xs tabular-nums text-muted">{posicion.codigo}</span>
      <span className="text-sm font-bold leading-snug">{posicion.puesto.nombre}</span>
      <span className={`text-[13px] ${posicion.ocupante ? 'font-medium' : 'italic text-muted'}`}>
        {posicion.ocupante ? (posicion.ocupante.nombre ?? organigramaText.noOccupant) : organigramaText.noOccupant}
      </span>
      <span className="mt-1">
        <Badge variant={vacante ? 'warning' : 'default'}>{organigramaText.statuses[posicion.estado]}</Badge>
      </span>
    </button>
  )
}
