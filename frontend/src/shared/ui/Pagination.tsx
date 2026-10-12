import { Button } from './Button'
import { uiText } from './text'

interface PaginationProps {
  page: number
  lastPage: number
  total: number
  from: number | null
  to: number | null
  onPageChange: (page: number) => void
}

/** Paginación del design system: resumen a la izquierda, navegación a la derecha. */
export function Pagination({ page, lastPage, total, from, to, onPageChange }: PaginationProps) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted">
      <span>{total === 0 ? uiText.pagination.noResults : uiText.pagination.showing(from ?? 0, to ?? 0, total)}</span>
      <nav aria-label={uiText.pagination.label} className="flex flex-wrap items-center gap-3">
        <Button variant="secondary" disabled={page <= 1} onClick={() => onPageChange(page - 1)}>
          {uiText.pagination.previous}
        </Button>
        <span className="whitespace-nowrap">
          {uiText.pagination.pageOf(page, Math.max(lastPage, 1))}
        </span>
        <Button variant="secondary" disabled={page >= lastPage} onClick={() => onPageChange(page + 1)}>
          {uiText.pagination.next}
        </Button>
      </nav>
    </div>
  )
}
