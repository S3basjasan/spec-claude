import { Link, useLocation } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Badge } from '../../../../shared/ui/Badge'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconClock } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { EstadoDeLaEntrevista } from '../components/Estados'
import { problemaDeSalida } from '../errores'
import { useMiDesvinculacion } from '../hooks/useOffboarding'
import { offboardingText } from '../text'

const text = offboardingText.mine

/** `/mi-desvinculacion`: lo que la organización comunicó de su salida y sus actividades, con la entrevista como una tarea más. */
export function MiDesvinculacionPage() {
  const consulta = useMiDesvinculacion()
  const aviso = (useLocation().state as { aviso?: string } | null)?.aviso

  let contenido
  if (consulta.isPending) {
    contenido = (
      <div role="status" aria-label={text.loading} className="flex flex-col gap-3">
        <Skeleton className="h-24" />
        <Skeleton className="h-32" />
      </div>
    )
  } else if (consulta.isError) {
    const problema = problemaDeSalida(consulta.error)
    contenido = problema.noEncontrado ? (
      <div className="rounded-xl border border-border bg-surface">
        <EmptyState icon={<IconClock size={24} />} title={problema.codigo === 'SIN_FICHA_ASOCIADA' ? text.noFicha : text.emptyTitle} description={problema.codigo === 'SIN_FICHA_ASOCIADA' ? '' : text.emptyDescription} />
      </div>
    ) : (
      <div className="rounded-xl border border-border bg-surface">
        <ErrorState title={text.errorTitle} description={text.errorDescription} retryLabel={offboardingText.retry} onRetry={() => void consulta.refetch()} />
      </div>
    )
  } else {
    const datos = consulta.data
    const entrevista = datos.entrevista
    contenido = (
      <>
        <dl aria-label={text.summary} className="grid grid-cols-1 gap-3 rounded-xl border border-border bg-surface p-5 sm:grid-cols-3">
          <div className="flex flex-col gap-0.5">
            <dt className="text-[13px] font-semibold text-muted">{text.reason}</dt>
            <dd className="text-[15px]">{offboardingText.motivos[datos.motivo.clave]}</dd>
          </div>
          <div className="flex flex-col gap-0.5">
            <dt className="text-[13px] font-semibold text-muted">{text.effectiveDate}</dt>
            <dd className="text-[15px]">{datos.fecha_efectiva}</dd>
          </div>
          <div className="flex flex-col gap-0.5">
            <dt className="text-[13px] font-semibold text-muted">{text.status}</dt>
            <dd className="text-[15px]">{datos.estado === 'Efectiva' ? text.statusEffective : text.statusScheduled}</dd>
          </div>
        </dl>

        <section aria-label={text.tasks} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
          <h2 className="text-lg font-bold">{text.tasks}</h2>
          <ol className="flex flex-col gap-2">
            {datos.tareas.map((tarea) => (
              <li key={tarea.orden} className="flex flex-wrap items-start justify-between gap-2 rounded-lg border border-border p-3">
                <div>
                  <p className="font-semibold">{tarea.nombre}</p>
                  {tarea.tipo === 'Entrevista' ? <p className="text-[13px] text-muted">{text.interviewCard}</p> : tarea.descripcion && <p className="text-[13px] text-muted">{tarea.descripcion}</p>}
                </div>
                <Badge variant={tarea.estado === 'Completada' ? 'default' : 'warning'}>{tarea.estado === 'Completada' ? text.taskDone : text.taskPending}</Badge>
              </li>
            ))}
          </ol>
        </section>

        <section aria-label={offboardingText.interviewForm.title} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
          <h2 className="text-lg font-bold">{offboardingText.interviewForm.title}</h2>
          <p>
            <EstadoDeLaEntrevista estado={entrevista.estado} />
          </p>
          {entrevista.responder_disponible ? (
            <div>
              <Link to="/mi-desvinculacion/entrevista" className="inline-flex min-h-11 items-center rounded-lg bg-accent px-5 text-[15px] font-semibold text-onaccent hover:bg-accent-hover">
                {entrevista.estado === 'EnCurso' ? text.interviewContinue : text.interviewAnswer}
              </Link>
            </div>
          ) : (
            <>
              <Alert tone="neutral">{text.interviewClosed}</Alert>
              {entrevista.estado === 'Completada' && (
                <div>
                  <Link to="/mi-desvinculacion/entrevista" className="font-semibold text-accent underline">
                    {text.interviewView}
                  </Link>
                </div>
              )}
            </>
          )}
        </section>
      </>
    )
  }

  return (
    <div className="flex flex-col gap-5">
      <header>
        <h1 className="text-2xl font-bold">{text.title}</h1>
        <p className="text-muted">{text.subtitle}</p>
      </header>
      {aviso && <Alert tone="neutral">{aviso}</Alert>}
      {contenido}
    </div>
  )
}
