import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { Diferencia, TipoPolitica } from '../api'
import { useComparacion } from '../hooks/usePoliticas'
import { politicasText } from '../text'
import { formatearValor } from '../validation'

const text = politicasText.compare

/** Lista de diferencias (antes/después); reutilizada por la comparación de una versión y por el resumen de publicación. */
export function ListaDeDiferencias({ diferencias, sinVigente }: { diferencias: Diferencia[]; sinVigente: boolean }) {
  if (sinVigente) return <p className="text-sm text-muted">{text.noCurrent}</p>
  if (diferencias.length === 0) return <p className="text-sm text-muted">{text.noDifferences}</p>

  return (
    <ul className="flex flex-col gap-2">
      {diferencias.map((d) => (
        <li key={d.clave} className="rounded-lg border border-border p-3">
          <p className="font-semibold">{d.etiqueta}</p>
          <p className="text-sm">
            <span className="text-muted">{text.before}: </span>
            {formatearValor(d.anterior)}
          </p>
          <p className="text-sm">
            <span className="text-muted">{text.after}: </span>
            {formatearValor(d.nuevo)}
          </p>
        </li>
      ))}
    </ul>
  )
}

/** Compara una versión con la vigente real del tipo. */
export function ComparacionDeVersiones({ tipo, versionId }: { tipo: TipoPolitica; versionId: number }) {
  const consulta = useComparacion(tipo, versionId)

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={text.loading}>
        <Skeleton className="h-16" />
      </div>
    )
  }
  if (consulta.isError) {
    return (
      <Alert>
        {text.error} {toApiError(consulta.error).message ?? ''}
      </Alert>
    )
  }

  return (
    <section aria-label={text.title} className="flex flex-col gap-2">
      <h3 className="text-sm font-bold">{text.title}</h3>
      <ListaDeDiferencias diferencias={consulta.data.diferencias} sinVigente={consulta.data.contra === null} />
    </section>
  )
}
