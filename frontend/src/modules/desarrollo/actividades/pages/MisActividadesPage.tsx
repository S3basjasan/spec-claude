import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconSchool } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { ResumenDelColaborador, SeccionesColaborador } from '../components/SeccionesColaborador'
import { useMisActividades } from '../hooks/useActividades'
import { actividadesText } from '../text'

const t = actividadesText.colaborador

/** «Mis actividades formativas» (Colaborador): resumen, próximas actividades e historial con su asistencia y calificación. */
export function MisActividadesPage() {
  const mias = useMisActividades()
  const sinActividades = mias.data !== undefined && mias.data.proximas.length === 0 && mias.data.historial.length === 0

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{t.title}</h1>
        <p className="mt-1.5 text-base text-muted">{t.subtitle}</p>
      </div>

      {mias.isPending ? (
        <div role="status" aria-label={actividadesText.loading} className="flex flex-col gap-2">
          {[0, 1, 2].map((fila) => (
            <Skeleton key={fila} className="h-24" />
          ))}
        </div>
      ) : mias.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={actividadesText.error.title} description={actividadesText.error.description} retryLabel={actividadesText.error.retry} onRetry={() => void mias.refetch()} />
        </div>
      ) : sinActividades ? (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconSchool size={24} />} title={t.vacio.title} description={t.vacio.description} />
        </div>
      ) : (
        <>
          <ResumenDelColaborador resumen={mias.data.resumen} />
          <SeccionesColaborador proximas={mias.data.proximas} historial={mias.data.historial} />
        </>
      )}
    </div>
  )
}
