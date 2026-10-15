import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconSchool } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { DetalleColaborador } from '../components/DetalleColaborador'
import { GrupoPorArea } from '../components/GrupoPorArea'
import { Recomendados } from '../components/Recomendados'
import { useCatalogoCompleto, useRecomendados } from '../hooks/useCatalogo'
import { useListadoParams } from '../hooks/useListadoParams'
import { catalogoText } from '../text'

const text = catalogoText.collaborator

/** Vista del Colaborador: recomendados arriba, tarjetas agrupadas por área y detalle de la competencia elegida. */
export function CatalogoColaboradorPage() {
  const { competencia, abrir } = useListadoParams()
  const catalogo = useCatalogoCompleto()
  // Si falla, la sección simplemente no se muestra: el resto del catálogo sigue funcionando.
  const recomendados = useRecomendados()

  if (competencia !== null) {
    return (
      <div className="mx-auto max-w-275">
        <DetalleColaborador competenciaId={competencia} onVolver={() => abrir(null)} />
      </div>
    )
  }

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
        <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
      </div>

      {catalogo.isPending ? (
        <div role="status" aria-label={text.loadingLabel} className="grid gap-4" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))' }}>
          {[0, 1, 2, 3].map((tarjeta) => (
            <Skeleton key={tarjeta} className="h-32" />
          ))}
        </div>
      ) : catalogo.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState
            title={text.errorTitle}
            description={text.errorDescription}
            retryLabel={text.retry}
            onRetry={() => void catalogo.refetch()}
          />
        </div>
      ) : catalogo.data.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconSchool size={24} />} title={text.emptyTitle} description={text.emptyDescription} />
        </div>
      ) : (
        <>
          <Recomendados items={recomendados.data ?? []} onAbrir={abrir} />
          <GrupoPorArea filas={catalogo.data} onAbrir={abrir} />
        </>
      )}
    </div>
  )
}
