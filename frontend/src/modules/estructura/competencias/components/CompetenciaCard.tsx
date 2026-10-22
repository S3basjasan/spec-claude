import { useId, type ReactNode } from 'react'
import { IconChevronDown, IconPower, IconCheck } from '../../../../shared/ui/icons'
import type { Competencia } from '../api'
import { competenciasText } from '../text'

const text = competenciasText.list

interface CompetenciaCardProps {
  competencia: Competencia
  abierta: boolean
  onToggle: () => void
  /** Solo el administrador ve Activar / Desactivar. */
  esAdmin: boolean
  onCambiarEstado: () => void
  /** El cambio de estado de esta competencia está en curso. */
  cambiando?: boolean
  /** Contenido desplegado (pestañas); solo se muestra con la tarjeta abierta. */
  children?: ReactNode
}

/**
 * Tarjeta de una competencia (design/DESIGN.md, listado de tarjetas con pestañas): la cabecera completa abre y
 * cierra el detalle; no muestra descripción ni estado, y el nombre se atenúa si la competencia está inactiva.
 */
export function CompetenciaCard({ competencia, abierta, onToggle, esAdmin, onCambiarEstado, cambiando = false, children }: CompetenciaCardProps) {
  const panelId = useId()
  const activa = competencia.estado === 'Activo'

  return (
    <article className="overflow-hidden rounded-xl border border-border bg-surface">
      <div className="flex min-h-16 items-stretch">
        <button
          type="button"
          aria-expanded={abierta}
          aria-controls={panelId}
          aria-label={abierta ? text.toggleClose(competencia.nombre) : text.toggleOpen(competencia.nombre)}
          onClick={onToggle}
          className="flex min-w-0 flex-1 cursor-pointer items-center gap-3 py-2.5 pl-5 pr-2 text-left hover:bg-soft"
        >
          <span className="flex min-w-0 flex-1 flex-col gap-0.5">
            <span className="text-[13px] text-muted">{competencia.codigo}</span>
            <span className={`text-base font-semibold ${activa ? '' : 'text-muted'}`}>{competencia.nombre}</span>
          </span>
          <span className="inline-block shrink-0 rounded-full bg-soft px-2.5 py-[3px] text-[13px] font-semibold text-accent">
            {competenciasText.types[competencia.tipo]}
          </span>
          <span aria-hidden="true" className={`inline-flex size-11 shrink-0 items-center justify-center text-muted transition-transform ${abierta ? 'rotate-180' : ''}`}>
            <IconChevronDown size={18} />
          </span>
        </button>
        {esAdmin && (
          <button
            type="button"
            aria-label={activa ? text.deactivate(competencia.nombre) : text.activate(competencia.nombre)}
            title={activa ? 'Desactivar' : 'Activar'}
            disabled={cambiando}
            onClick={onCambiarEstado}
            className={`my-auto mr-2 inline-flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg hover:bg-soft disabled:cursor-not-allowed disabled:opacity-60 ${
              activa ? 'text-danger' : 'text-accent'
            }`}
          >
            {activa ? <IconPower size={18} /> : <IconCheck size={18} />}
          </button>
        )}
      </div>
      {abierta && (
        <div id={panelId} className="border-t border-border bg-bg px-5 pb-5">
          {children}
        </div>
      )}
    </article>
  )
}
