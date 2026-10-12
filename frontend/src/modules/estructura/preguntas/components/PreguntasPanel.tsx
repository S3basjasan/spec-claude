import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { usePregunta } from '../hooks/usePreguntas'
import { preguntasText } from '../text'

interface PreguntasPanelProps {
  competenciaId: number
  nombre: string
  panelId: string
}

/** Las cuatro preguntas numeradas de una competencia; se piden al servidor solo al desplegar. */
export function PreguntasPanel({ competenciaId, nombre, panelId }: PreguntasPanelProps) {
  const { data, isPending, isError, error, refetch } = usePregunta(competenciaId)
  const text = preguntasText.panel

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
      {data.estado === 'Inactivo' && <Alert tone="neutral">{preguntasText.inactiveNotice}</Alert>}
      <h3 className="font-semibold">{text.title}</h3>
      <ol className="list-decimal space-y-1.5 pl-5">
        {data.preguntas.map((pregunta, indice) => (
          <li key={indice}>{pregunta}</li>
        ))}
      </ol>
    </section>
  )
}
