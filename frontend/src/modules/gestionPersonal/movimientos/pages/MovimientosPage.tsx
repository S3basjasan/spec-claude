import { Link, useLocation } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { ResumenActuaciones } from '../api'
import { FiltrosDeActuaciones } from '../components/FiltrosDeActuaciones'
import { TablaDeActuaciones } from '../components/TablaDeActuaciones'
import { useFiltrosDeActuaciones } from '../hooks/useFiltrosDeActuaciones'
import { useListadoDeActuaciones, useResumenDeActuaciones } from '../hooks/useActuaciones'
import { movimientosText } from '../text'

const text = movimientosText.list

const TARJETAS: { clave: keyof ResumenActuaciones; grupo: string }[] = [
  { clave: 'pendientes', grupo: 'pendientes' },
  { clave: 'en_revision', grupo: 'pendientes' },
  { clave: 'programados', grupo: 'programados' },
  { clave: 'aplicados', grupo: 'aplicados' },
  { clave: 'incidencias', grupo: 'incidencias' },
]

function Resumen() {
  const resumen = useResumenDeActuaciones()
  const { actualizar } = useFiltrosDeActuaciones()

  if (resumen.isPending) return <Skeleton className="h-20" />
  if (resumen.isError) return <Alert>{text.summaryError}</Alert>

  return (
    <ul aria-label={text.summaryLabel} className="grid grid-cols-2 gap-3 md:grid-cols-5">
      {TARJETAS.map(({ clave, grupo }) => (
        <li key={clave}>
          <button
            type="button"
            onClick={() => actualizar({ grupo, estado: clave === 'en_revision' ? 'EnRevision' : 'todos' })}
            className="flex min-h-11 w-full flex-col items-start gap-1 rounded-xl border border-border bg-surface p-3 text-left hover:bg-soft"
          >
            <span className="text-2xl font-bold">{resumen.data[clave]}</span>
            <span className="text-[13px] text-muted">{text.summary[clave]}</span>
          </button>
        </li>
      ))}
    </ul>
  )
}

/** `/movimientos-personal`: listado del Administrador con resumen, filtros en la URL y estados distinguibles. */
export function MovimientosPage() {
  const { valores, filtros, page, actualizar, limpiar } = useFiltrosDeActuaciones()
  const listado = useListadoDeActuaciones(filtros)
  const aviso = (useLocation().state as { aviso?: string } | null)?.aviso

  let contenido
  if (listado.isPending) {
    contenido = (
      <div role="status" aria-label={text.loading} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2, 3].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  } else if (listado.isError) {
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        <ErrorState title={text.errorTitle} description={text.errorDescription} retryLabel={movimientosText.retry} onRetry={() => void listado.refetch()} />
      </div>
    )
  } else if (listado.data.data.length === 0) {
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        <EmptyState icon={<IconFileText size={24} />} title={text.emptyTitle} description={text.emptyDescription} />
      </div>
    )
  } else {
    const { meta } = listado.data
    contenido = (
      <>
        <TablaDeActuaciones filas={listado.data.data} />
        <Pagination page={meta.current_page} lastPage={meta.last_page} total={meta.total} from={meta.from} to={meta.to} onPageChange={(p) => actualizar({}, p)} />
      </>
    )
  }

  return (
    <div className="flex flex-col gap-5">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{text.title}</h1>
          <p className="text-muted">{text.subtitle}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link to="/movimientos-personal/proponer" className="inline-flex min-h-11 items-center rounded-lg bg-accent px-5 text-[15px] font-semibold text-onaccent hover:bg-accent-hover">
            {text.propose}
          </Link>
          <Link to="/movimientos-personal/novedad" className="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-5 text-[15px] font-semibold hover:bg-soft">
            {text.register}
          </Link>
          <Link to="/movimientos-personal/novedad?tipo=Ausencia" className="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-5 text-[15px] font-semibold hover:bg-soft">
            {text.inform}
          </Link>
        </div>
      </header>

      {aviso && <Alert tone="neutral">{aviso}</Alert>}
      <Resumen />
      <FiltrosDeActuaciones valores={valores} onChange={(cambios) => actualizar(cambios)} onClear={limpiar} />
      {page > 1 && listado.isFetching && <span className="sr-only" role="status">{text.loading}</span>}
      {contenido}
    </div>
  )
}
