import { useId } from 'react'
import { Badge } from '../../../../shared/ui/Badge'
import type { Recomendado } from '../api'
import { catalogoText } from '../text'

interface RecomendadosProps {
  items: Recomendado[]
  onAbrir: (id: number) => void
}

/**
 * Sección destacada "Recomendados para cerrar mis brechas". No se muestra si no hay recomendados (sin brechas
 * activas, sin DNF o sin oferta formativa para ellas).
 */
export function Recomendados({ items, onAbrir }: RecomendadosProps) {
  const titulo = useId()
  const text = catalogoText.recommended

  if (items.length === 0) return null

  return (
    <section aria-labelledby={titulo} className="flex flex-col gap-3 rounded-xl border border-border bg-soft p-5">
      <div>
        <h2 id={titulo} className="text-lg font-bold">
          {text.title}
        </h2>
        <p className="text-sm text-muted">{text.subtitle(items.length)}</p>
      </div>
      <ul className="m-0 grid list-none gap-3 p-0" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))' }}>
        {items.map((item) => (
          <li key={item.competencia_id}>
            <button
              type="button"
              onClick={() => onAbrir(item.competencia_id)}
              className="flex h-full w-full flex-col gap-2 rounded-lg border border-border bg-surface p-4 text-left hover:bg-soft"
            >
              <span className="flex flex-wrap items-center gap-2">
                <span className="text-[15px] font-bold">{item.nombre}</span>
                <Badge>{catalogoText.tipoCompetencia[item.tipo]}</Badge>
              </span>
              <span className="text-[13px] text-muted">{text.gap(item.nivel_actual, item.nivel_requerido)}</span>
              <span className="text-[13px] font-semibold text-accent">
                {catalogoText.collaborator.cardCourses(item.cursos_count)}
                <span className="sr-only">. {text.open(item.nombre)}</span>
              </span>
            </button>
          </li>
        ))}
      </ul>
    </section>
  )
}
