import { Link } from 'react-router-dom'
import { Badge } from '../../../../shared/ui/Badge'
import { IconClock, IconUser, IconX } from '../../../../shared/ui/icons'
import type { Recurso } from '../api'
import { catalogoText } from '../text'

interface RecursoItemProps {
  recurso: Recurso
  /** Si se indica, es una fila de gestión (Administrador): muestra Quitar en lugar de Iniciar/Ver. */
  onQuitar?: () => void
}

const BOTON_BASE =
  'inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg px-4 text-[15px] font-semibold'

/**
 * Curso o micro-contenido con su tipo, duración y formador. Para el Colaborador lleva el botón Iniciar/Ver; sin
 * destino, ese botón se muestra deshabilitado con "Próximamente". Para el Administrador lleva Quitar.
 */
export function RecursoItem({ recurso, onQuitar }: RecursoItemProps) {
  const { nombre } = recurso
  const accion = catalogoText.accion[recurso.accion]

  let control
  if (onQuitar) {
    control = (
      <button
        type="button"
        aria-label={catalogoText.panel.remove(nombre)}
        onClick={onQuitar}
        className="inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-muted hover:bg-soft"
      >
        <IconX size={18} />
      </button>
    )
  } else if (recurso.url_destino) {
    const clases = `${BOTON_BASE} bg-accent text-onaccent hover:bg-accent-hover`
    control = recurso.url_destino.startsWith('/') ? (
      <Link to={recurso.url_destino} className={clases} aria-label={`${accion} ${nombre}`}>
        {accion}
      </Link>
    ) : (
      <a href={recurso.url_destino} className={clases} aria-label={`${accion} ${nombre}`}>
        {accion}
      </a>
    )
  } else {
    control = (
      <button
        type="button"
        disabled
        aria-label={`${accion} ${nombre}: ${catalogoText.proximamente}`}
        className={`${BOTON_BASE} cursor-not-allowed border border-border bg-surface text-muted opacity-60`}
      >
        {catalogoText.proximamente}
      </button>
    )
  }

  return (
    <li className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-surface px-4 py-3">
      <div className="flex min-w-0 flex-1 flex-col gap-1.5">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-[15px] font-semibold">{nombre}</span>
          <Badge>{catalogoText.tipoRecurso[recurso.tipo] ?? recurso.tipo}</Badge>
          {recurso.estado === 'Archivado' && <Badge variant="neutral">{catalogoText.archivado}</Badge>}
        </div>
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] text-muted">
          <span className="inline-flex items-center gap-1">
            <IconClock size={14} />
            {catalogoText.duracion(recurso.duracion_minutos)}
          </span>
          {recurso.formador && (
            <span className="inline-flex items-center gap-1">
              <IconUser size={14} />
              {recurso.formador}
            </span>
          )}
        </div>
      </div>
      {control}
    </li>
  )
}
