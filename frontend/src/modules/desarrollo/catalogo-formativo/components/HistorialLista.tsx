import { useState } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { uiText } from '../../../../shared/ui/text'
import { formatearFecha } from '../fecha'
import { useHistorial } from '../hooks/useCatalogo'
import { catalogoText } from '../text'

const text = catalogoText.history

/** Historial de cambios de la competencia (solo lectura), del más reciente al más antiguo. */
export function HistorialLista({ competenciaId }: { competenciaId: number }) {
  const [pagina, setPagina] = useState(1)
  const consulta = useHistorial(competenciaId, pagina)

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={text.loadingLabel} className="flex flex-col gap-2">
        <Skeleton className="h-10" />
        <Skeleton className="h-10" />
      </div>
    )
  }

  if (consulta.isError) {
    return (
      <div role="alert" className="flex flex-wrap items-center gap-3 text-sm text-danger">
        <span>{text.errorTitle}</span>
        <Button variant="secondary" onClick={() => void consulta.refetch()}>
          {text.retry}
        </Button>
      </div>
    )
  }

  const { data, meta } = consulta.data
  if (data.length === 0) return <p className="text-sm text-muted">{text.empty}</p>

  return (
    <div className="flex flex-col gap-3">
      <ul aria-label={text.listLabel} className="m-0 flex list-none flex-col gap-2 p-0">
        {data.map((entrada, indice) => (
          <li key={`${entrada.fecha}-${indice}`} className="rounded-lg border border-border px-3.5 py-2.5 text-sm">
            <p>{text.describe(entrada)}</p>
            <p className="text-[13px] text-muted">
              {entrada.usuario} · {formatearFecha(entrada.fecha)}
            </p>
          </li>
        ))}
      </ul>
      {meta.last_page > 1 && (
        <nav aria-label={uiText.pagination.label} className="flex flex-wrap items-center gap-3 text-sm text-muted">
          <Button variant="secondary" disabled={pagina <= 1} onClick={() => setPagina(pagina - 1)}>
            {uiText.pagination.previous}
          </Button>
          <span>{uiText.pagination.pageOf(meta.current_page, meta.last_page)}</span>
          <Button variant="secondary" disabled={pagina >= meta.last_page} onClick={() => setPagina(pagina + 1)}>
            {uiText.pagination.next}
          </Button>
        </nav>
      )}
    </div>
  )
}
