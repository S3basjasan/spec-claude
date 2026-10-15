import type { ReactNode } from 'react'
import { Badge } from '../../../../shared/ui/Badge'
import { IconAlertTriangle, IconChevronRight, IconCornerDownRight, IconPencil, IconPlus, IconPower, IconRotate } from '../../../../shared/ui/icons'
import type { UnidadResumen } from '../api'
import { unidadesText } from '../text'

/** Sangría de cada nivel del árbol (design/DESIGN.md §5 Listado en árbol). */
export const SANGRIA_POR_NIVEL_PX = 24

interface UnidadFilaProps {
  unidad: UnidadResumen
  /** Solo el Administrador ve la celda de acciones. */
  esAdmin: boolean
  expandida: boolean
  /** Hay búsqueda o filtro de inactivas: las ramas se muestran expandidas y no se pueden contraer. */
  expansionForzada: boolean
  /** Texto de la advertencia "No sigue la cadena", si corresponde. */
  advertencia?: string
  /** Hay un cambio de estado en curso para esta unidad. */
  ocupada?: boolean
  onAlternar?: (unidad: UnidadResumen) => void
  onEditar?: (unidad: UnidadResumen) => void
  onAgregar?: (unidad: UnidadResumen) => void
  onDesactivar?: (unidad: UnidadResumen) => void
  onReactivar?: (unidad: UnidadResumen) => void
}

interface BotonDeFilaProps {
  etiqueta: string
  icono: ReactNode
  /** Motivo por el que está deshabilitado; se expone como descripción accesible. */
  motivo?: string
  descripcionId: string
  peligro?: boolean
  acento?: boolean
  ocupado?: boolean
  onClick?: () => void
}

/** Botón de icono de la fila: nombre accesible con el registro y, deshabilitado, su motivo como descripción. */
function BotonDeFila({ etiqueta, icono, motivo, descripcionId, peligro, acento, ocupado, onClick }: BotonDeFilaProps) {
  const deshabilitado = motivo !== undefined || ocupado === true

  return (
    <>
      <button
        type="button"
        aria-label={etiqueta}
        aria-describedby={motivo ? descripcionId : undefined}
        aria-busy={ocupado || undefined}
        title={motivo ?? etiqueta}
        disabled={deshabilitado}
        onClick={onClick}
        className={`inline-flex size-11 cursor-pointer items-center justify-center rounded-lg border border-transparent hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:bg-transparent ${
          peligro ? 'text-danger' : acento ? 'text-accent' : 'text-text'
        }`}
      >
        {icono}
      </button>
      {motivo && (
        <span id={descripcionId} className="sr-only">
          {motivo}
        </span>
      )}
    </>
  )
}

/** Fila del árbol: nombre con sangría, control de expandir e indicador de dependencia; acciones solo para el Administrador. */
export function UnidadFila({
  unidad,
  esAdmin,
  expandida,
  expansionForzada,
  advertencia,
  ocupada = false,
  onAlternar,
  onEditar,
  onAgregar,
  onDesactivar,
  onReactivar,
}: UnidadFilaProps) {
  const text = unidadesText.list
  const acciones = unidadesText.actions
  const activa = unidad.estado === 'Activa'
  const esRaiz = unidad.unidad_padre === null
  const base = `unidad-${unidad.id}`

  const motivoAgregar = !unidad.admite_hijas ? acciones.addSubLast : !activa ? acciones.addSubInactive : undefined
  const motivoDesactivar = esRaiz ? acciones.deactivateRoot : undefined
  const motivoReactivar = unidad.unidad_padre?.estado === 'Inactiva' ? acciones.reactivateParent : undefined

  return (
    <tr className="border-t border-border hover:bg-soft">
      <th scope="row" className="px-5 py-1.5 text-left font-semibold">
        <span className="inline-flex items-center gap-1" style={{ paddingLeft: `${unidad.nivel_arbol * SANGRIA_POR_NIVEL_PX}px` }}>
          <span className="sr-only">{text.level(unidad.nivel_arbol)}</span>
          {unidad.tiene_dependientes ? (
            <button
              type="button"
              aria-expanded={expandida || expansionForzada}
              aria-label={expandida || expansionForzada ? text.collapse(unidad.nombre) : text.expand(unidad.nombre)}
              title={expansionForzada ? text.expandDisabled : undefined}
              disabled={expansionForzada}
              onClick={() => onAlternar?.(unidad)}
              className="inline-flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-muted hover:bg-surface disabled:cursor-not-allowed disabled:opacity-60"
            >
              <IconChevronRight size={18} className={`transition-transform ${expandida || expansionForzada ? 'rotate-90' : ''}`} />
            </button>
          ) : (
            <span aria-hidden="true" className="inline-flex size-11 shrink-0 items-center justify-center text-muted">
              {unidad.nivel_arbol > 0 && <IconCornerDownRight size={16} />}
            </span>
          )}
          {unidad.nombre}
        </span>
      </th>
      <td className="px-5 py-3.5 text-sm tabular-nums text-muted">{unidad.codigo}</td>
      <td className="px-5 py-3.5">
        <span className="inline-flex items-center gap-2">
          <Badge>{unidad.tipo.nombre}</Badge>
          {advertencia && (
            <span role="img" aria-label={advertencia} title={advertencia} className="inline-flex text-warning">
              <IconAlertTriangle size={18} />
            </span>
          )}
        </span>
      </td>
      <td className="px-5 py-3.5">
        <Badge variant={activa ? 'default' : 'neutral'}>{unidadesText.statuses[unidad.estado]}</Badge>
      </td>
      {esAdmin && (
        <td className="px-3 py-1.5 text-right">
          <div className="flex items-center justify-end gap-1">
            <BotonDeFila etiqueta={acciones.edit(unidad.nombre)} icono={<IconPencil size={18} />} descripcionId={`${base}-editar`} onClick={() => onEditar?.(unidad)} />
            <BotonDeFila
              etiqueta={acciones.addSub(unidad.nombre)}
              icono={<IconPlus size={18} />}
              motivo={motivoAgregar}
              descripcionId={`${base}-agregar`}
              onClick={() => onAgregar?.(unidad)}
            />
            {activa ? (
              <BotonDeFila
                etiqueta={acciones.deactivate(unidad.nombre)}
                icono={<IconPower size={18} />}
                motivo={motivoDesactivar}
                descripcionId={`${base}-desactivar`}
                peligro
                ocupado={ocupada}
                onClick={() => onDesactivar?.(unidad)}
              />
            ) : (
              <BotonDeFila
                etiqueta={acciones.reactivate(unidad.nombre)}
                icono={<IconRotate size={18} />}
                motivo={motivoReactivar}
                descripcionId={`${base}-reactivar`}
                acento
                ocupado={ocupada}
                onClick={() => onReactivar?.(unidad)}
              />
            )}
          </div>
        </td>
      )}
    </tr>
  )
}
