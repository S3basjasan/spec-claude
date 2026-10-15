import { Fragment } from 'react'
import { IconChevronRight } from '../../../../shared/ui/icons'
import type { BorradorDeTipo, TipoUnidad } from '../api'
import { unidadesText } from '../text'

const text = unidadesText.editTypes

interface VistaPreviaDeCadenaProps {
  actual: TipoUnidad[]
  resultante: BorradorDeTipo[]
}

/**
 * Cadena actual y cadena resultante del borrador, resaltando los tipos que cambian de nivel o de nombre (con la marca
 * "cambia" y un fondo suave, no solo con color).
 */
export function VistaPreviaDeCadena({ actual, resultante }: VistaPreviaDeCadenaProps) {
  const actualPorId = new Map(actual.map((tipo) => [tipo.id, tipo]))

  return (
    <section aria-label={text.previewTitle} className="flex flex-col gap-3 rounded-lg border border-border bg-bg p-4">
      <h3 className="text-sm font-semibold">{text.previewTitle}</h3>
      <Cadena titulo={text.previewCurrent} items={actual.map((tipo) => ({ id: tipo.id, nombre: tipo.nombre, nivel: tipo.nivel, cambia: false }))} />
      <Cadena
        titulo={text.previewResult}
        items={resultante.map((tipo, indice) => {
          const previo = actualPorId.get(tipo.id)
          return {
            id: tipo.id,
            nombre: tipo.nombre.trim() || '…',
            nivel: indice + 1,
            cambia: previo !== undefined && (previo.nombre !== tipo.nombre.trim() || previo.nivel !== indice + 1),
          }
        })}
      />
    </section>
  )
}

function Cadena({ titulo, items }: { titulo: string; items: { id: number; nombre: string; nivel: number; cambia: boolean }[] }) {
  return (
    <div>
      <p className="mb-1 text-[13px] text-muted">{titulo}</p>
      <ol className="m-0 flex list-none flex-wrap items-center gap-1.5 p-0">
        {items.map((item, indice) => (
          <Fragment key={item.id}>
            <li
              className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[13px] ${
                item.cambia ? 'border-accent bg-soft font-semibold' : 'border-border bg-surface'
              }`}
            >
              <span className="text-xs text-accent">{item.nivel}</span>
              {item.nombre}
              {item.cambia && <span className="text-xs font-semibold text-accent">({text.changedMark})</span>}
            </li>
            {indice < items.length - 1 && <IconChevronRight size={14} className="text-muted" />}
          </Fragment>
        ))}
      </ol>
    </div>
  )
}
