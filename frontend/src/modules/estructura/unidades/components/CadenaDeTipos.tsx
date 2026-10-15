import { Fragment } from 'react'
import { IconChevronRight, IconPencil } from '../../../../shared/ui/icons'
import type { TipoUnidad } from '../api'
import { unidadesText } from '../text'

const text = unidadesText.chain

interface CadenaDeTiposProps {
  tipos: TipoUnidad[]
  /** Solo el Administrador ve "Editar tipos". */
  esAdmin: boolean
  onEditar?: () => void
}

/** Chips de los tipos por nivel, del tipo raíz hacia abajo; visible para todos. */
export function CadenaDeTipos({ tipos, esAdmin, onEditar }: CadenaDeTiposProps) {
  return (
    <section aria-labelledby="cadena-titulo" className="flex flex-col gap-2 rounded-xl border border-border bg-surface px-5 py-3.5">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 id="cadena-titulo" className="text-sm font-semibold text-muted">
          {text.title}
        </h2>
        {esAdmin && (
          <button
            type="button"
            onClick={onEditar}
            className="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-lg border border-border bg-surface px-3.5 text-sm font-medium hover:bg-soft"
          >
            <IconPencil size={16} />
            {text.editTypes}
          </button>
        )}
      </div>
      <ol className="m-0 flex list-none flex-wrap items-center gap-2 p-0">
        {tipos.map((tipo, indice) => (
          <Fragment key={tipo.id}>
            <li className="inline-flex items-center gap-2 rounded-full border border-border bg-soft px-3 py-1 text-[13px] font-semibold">
              <span aria-hidden="true" className="inline-flex size-5 items-center justify-center rounded-full bg-surface text-xs text-accent">
                {tipo.nivel}
              </span>
              <span className="sr-only">{text.levelLabel(tipo.nivel, tipo.nombre)}</span>
              <span aria-hidden="true">{tipo.nombre}</span>
            </li>
            {indice < tipos.length - 1 && <IconChevronRight size={16} className="text-muted" />}
          </Fragment>
        ))}
      </ol>
      <p className="text-[13px] text-muted">{text.help}</p>
    </section>
  )
}
