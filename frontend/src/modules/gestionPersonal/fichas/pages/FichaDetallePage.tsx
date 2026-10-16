import { Link, useLocation, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Badge } from '../../../../shared/ui/Badge'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconPencil } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { FichaContenido } from '../components/FichaContenido'
import { useColaborador } from '../hooks/useColaboradores'
import { fichasText } from '../text'

const text = fichasText.detail

export function FichaDetallePage() {
  const { colaboradorId } = useParams()
  const location = useLocation()
  const consulta = useColaborador(Number(colaboradorId))
  const aviso = (location.state as { aviso?: string } | null)?.aviso

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={text.loading} className="mx-auto flex max-w-225 flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2, 3].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  }

  if (consulta.isError) {
    const noExiste = toApiError(consulta.error).status === 404
    return (
      <div className="mx-auto max-w-225 rounded-xl border border-border bg-surface">
        <ErrorState
          title={noExiste ? text.notFoundTitle : text.errorTitle}
          description={noExiste ? text.notFoundDescription : text.errorDescription}
          retryLabel={fichasText.retry}
          onRetry={() => void consulta.refetch()}
        />
        <p className="pb-6 text-center">
          <Link to="/colaboradores" className="font-semibold text-accent underline">
            {text.backToList}
          </Link>
        </p>
      </div>
    )
  }

  const ficha = consulta.data
  const nombre = `${ficha.nombres} ${ficha.apellidos}`
  const estado = ficha.laboral.estado === null ? null : (fichasText.statuses[ficha.laboral.estado] ?? ficha.laboral.estado)

  return (
    <div className="mx-auto flex max-w-225 flex-col gap-5">
      <Breadcrumb
        label={text.breadcrumbLabel}
        items={[{ label: fichasText.navLabel, to: '/colaboradores' }, { label: nombre }]}
      />

      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{nombre}</h1>
          <p className="mt-1.5 flex flex-wrap items-center gap-3 text-base text-muted">
            <span className="font-semibold">{ficha.codigo}</span>
            {estado !== null && <Badge variant="neutral">{estado}</Badge>}
          </p>
        </div>
        {ficha.acciones.editar && (
          <Link
            to={`/colaboradores/${ficha.id}/editar`}
            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-5 text-[15px] font-semibold hover:bg-soft"
          >
            <IconPencil size={18} />
            {text.edit}
          </Link>
        )}
      </div>

      {aviso && (
        <p role="status" className="rounded-lg bg-soft px-3.5 py-3 text-sm font-semibold text-accent">
          {aviso}
        </p>
      )}

      <FichaContenido ficha={ficha} onReintentar={() => void consulta.refetch()} />
    </div>
  )
}
