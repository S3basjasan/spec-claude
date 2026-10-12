import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { LETRAS_GRADO } from '../api'
import { useComportamiento } from '../hooks/useComportamientos'
import { comportamientosText } from '../text'

interface ComportamientosPanelProps {
  competenciaId: number
  nombre: string
  panelId: string
}

/** Comportamientos de los cuatro grados como viñetas; se piden al servidor solo al desplegar. */
export function ComportamientosPanel({ competenciaId, nombre, panelId }: ComportamientosPanelProps) {
  const { data, isPending, isError, error, refetch } = useComportamiento(competenciaId)
  const text = comportamientosText.panel

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
      {data.estado === 'Inactivo' && <Alert tone="neutral">{comportamientosText.inactiveNotice}</Alert>}
      <h3 className="font-semibold">{text.title}</h3>
      <div className="grid gap-4 sm:grid-cols-2">
        {LETRAS_GRADO.map((letra) => (
          <div key={letra}>
            <h4 className="font-semibold text-accent">{text.gradeLabel(letra)}</h4>
            <ul className="mt-1 list-disc space-y-1 pl-5">
              {data.comportamientos[letra].map((comportamiento, indice) => (
                <li key={indice}>{comportamiento}</li>
              ))}
            </ul>
          </div>
        ))}
      </div>
    </section>
  )
}
