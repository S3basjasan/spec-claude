import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { NIVELES } from '../api'
import { useDetalleFormativo } from '../hooks/useCatalogo'
import { catalogoText } from '../text'
import { RecursoItem } from './RecursoItem'

interface DetalleColaboradorProps {
  competenciaId: number
  onVolver: () => void
}

const text = catalogoText.detail

/** Detalle de una competencia para el Colaborador: grados, cursos y micro-contenidos disponibles. */
export function DetalleColaborador({ competenciaId, onVolver }: DetalleColaboradorProps) {
  const consulta = useDetalleFormativo(competenciaId)

  const volver = (
    <Button variant="secondary" onClick={onVolver} className="self-start">
      {text.back}
    </Button>
  )

  if (consulta.isPending) {
    return (
      <div className="flex flex-col gap-4">
        {volver}
        <div role="status" aria-label={text.loadingLabel} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
          {[0, 1, 2].map((fila) => (
            <Skeleton key={fila} className="h-10" />
          ))}
        </div>
      </div>
    )
  }

  if (consulta.isError) {
    const noExiste = toApiError(consulta.error).status === 404
    return (
      <div className="flex flex-col gap-4">
        {volver}
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState
            title={noExiste ? text.notFoundTitle : text.errorTitle}
            description={noExiste ? text.notFoundDescription : text.errorDescription}
            retryLabel={text.retry}
            onRetry={() => void consulta.refetch()}
          />
        </div>
      </div>
    )
  }

  const detalle = consulta.data
  const { competencia } = detalle
  const sinOferta = detalle.cursos.length === 0 && detalle.microcontenidos.length === 0

  return (
    <div className="flex flex-col gap-5">
      {volver}

      <header className="flex flex-col gap-2">
        <p className="text-sm text-muted">{detalle.area_formativa?.nombre ?? catalogoText.sinArea}</p>
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{competencia.nombre}</h1>
          <Badge>{catalogoText.tipoCompetencia[competencia.tipo]}</Badge>
        </div>
        <p className="text-base text-muted">{competencia.descripcion}</p>
        <p className="text-sm font-semibold">
          {detalle.nivel_actual ? `${text.currentMark}: ${detalle.nivel_actual}` : catalogoText.collaborator.noLevel}
          {detalle.nivel_requerido && ` · ${text.requiredMark}: ${detalle.nivel_requerido}`}
        </p>
      </header>

      <section aria-labelledby="detalle-grados" className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        <h2 id="detalle-grados" className="text-base font-bold">
          {text.gradesTitle}
        </h2>
        <dl className="m-0 flex flex-col gap-2">
          {NIVELES.map((nivel) => (
            <div key={nivel} className="flex gap-3">
              <dt
                className={`flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                  nivel === detalle.nivel_actual ? 'bg-accent text-onaccent' : 'bg-border text-muted'
                }`}
              >
                {nivel}
              </dt>
              <dd className="m-0 text-sm">{competencia.grados[nivel]}</dd>
            </div>
          ))}
        </dl>
      </section>

      {sinOferta && <Alert tone="neutral">{text.noOffer}</Alert>}

      <section aria-labelledby="detalle-cursos" className="flex flex-col gap-3">
        <h2 id="detalle-cursos" className="text-lg font-bold">
          {text.coursesTitle(detalle.cursos.length)}
        </h2>
        {detalle.cursos.length === 0 ? (
          <p className="text-sm text-muted">{text.emptyCourses}</p>
        ) : (
          <ul aria-labelledby="detalle-cursos" className="m-0 flex list-none flex-col gap-2 p-0">
            {detalle.cursos.map((curso) => (
              <RecursoItem key={curso.id} recurso={curso} />
            ))}
          </ul>
        )}
      </section>

      <section aria-labelledby="detalle-micro" className="flex flex-col gap-3">
        <h2 id="detalle-micro" className="text-lg font-bold">
          {text.microTitle(detalle.microcontenidos.length)}
        </h2>
        <p className="text-[13px] text-muted">{catalogoText.notaMicrocontenidos}</p>
        {detalle.microcontenidos.length === 0 ? (
          <p className="text-sm text-muted">{text.emptyMicro}</p>
        ) : (
          <ul aria-labelledby="detalle-micro" className="m-0 flex list-none flex-col gap-2 p-0">
            {detalle.microcontenidos.map((micro) => (
              <RecursoItem key={micro.id} recurso={micro} />
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}
