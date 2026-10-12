import { toApiError } from '../../../../shared/api/httpClient'
import { Button } from '../../../../shared/ui/Button'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useCompetencia } from '../hooks/useCompetencias'
import { competenciasText } from '../text'

interface GradosPanelProps {
  competenciaId: number
  nombre: string
  panelId: string
}

/** Descripción completa y cuatro grados de una competencia; se piden al servidor solo al desplegar. */
export function GradosPanel({ competenciaId, nombre, panelId }: GradosPanelProps) {
  const { data, isPending, isError, error, refetch } = useCompetencia(competenciaId)
  const text = competenciasText.grades

  if (isPending) {
    return (
      <div id={panelId} role="status" aria-label={text.loading} className="flex flex-col gap-2">
        <Skeleton className="h-4 w-2/3" />
        <Skeleton className="h-4 w-1/2" />
        <Skeleton className="h-4 w-3/5" />
      </div>
    )
  }

  if (isError) {
    const noExiste = toApiError(error).status === 404
    return (
      <div id={panelId} role="alert" className="flex flex-wrap items-center gap-3 text-sm text-danger">
        <span>{noExiste ? text.notFound : text.errorTitle}</span>
        {!noExiste && (
          <Button variant="secondary" onClick={() => void refetch()}>
            {text.retry}
          </Button>
        )}
      </div>
    )
  }

  return (
    <section id={panelId} aria-label={text.regionLabel(nombre)} className="flex flex-col gap-3 text-sm">
      <p>
        <span className="font-semibold">{text.codeLabel}: </span>
        {data.codigo}
      </p>
      <p>
        <span className="font-semibold">{text.descriptionLabel}: </span>
        {data.descripcion}
      </p>
      <h3 className="font-semibold">{text.title}</h3>
      <dl className="grid gap-2 sm:grid-cols-[auto_1fr] sm:gap-x-4">
        {data.grados.map((grado) => (
          <div key={grado.grado} className="contents">
            <dt className="font-semibold text-accent">{text.gradeLabel(grado.grado)}</dt>
            <dd className="mb-2 sm:mb-0">{grado.descripcion}</dd>
          </div>
        ))}
      </dl>
    </section>
  )
}
