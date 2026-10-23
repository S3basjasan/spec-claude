import { useSearchParams } from 'react-router-dom'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { QueSigue } from '../components/QueSigue'
import { TarjetaDePlan } from '../components/TarjetaDePlan'
import { VistaDeMiPlan } from '../components/VistaDeMiPlan'
import { useMiPlan } from '../hooks/usePdi'
import { pdiText } from '../text'

const t = pdiText.mi

/** «Mi plan de desarrollo»: tarjetas de los planes de la persona (solo con el acuerdo del responsable), detalle y «Qué sigue». */
export function MiPlanDeDesarrolloPage() {
  const consulta = useMiPlan()
  const [params, setParams] = useSearchParams()

  return (
    <div className="mx-auto flex max-w-[1200px] flex-col gap-5">
      <header>
        {/* <p className="m-0 text-[13px] font-semibold text-muted">{pdiText.sobretitulo}</p> */}
        <h1 className="m-0 mt-0.5 text-2xl font-bold">{t.titulo}</h1>
        <p className="m-0 mt-1 text-muted">{t.descripcion}</p>
      </header>

      {consulta.isPending && (
        <div role="status" aria-label={pdiText.loading} className="flex flex-col gap-3">
          <Skeleton className="h-24" />
          <Skeleton className="h-40" />
        </div>
      )}

      {consulta.isError && (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={t.error.titulo} description={t.error.descripcion} retryLabel={t.error.reintentar} onRetry={() => void consulta.refetch()} />
        </div>
      )}

      {consulta.isSuccess && consulta.data.planes.length === 0 && (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconFileText />} title={t.vacio.titulo} description={t.vacio.descripcion} />
        </div>
      )}

      {consulta.isSuccess && consulta.data.planes.length > 0 && (() => {
        const { planes, que_sigue: queSigue } = consulta.data
        const pedido = Number(params.get('plan'))
        const elegido = planes.find((p) => p.id === pedido) ?? planes[0]

        return (
          <>
            <section aria-label={t.tarjetas} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {planes.map((p) => (
                <TarjetaDePlan key={p.id} plan={p} seleccionado={p.id === elegido.id} onElegir={() => setParams({ plan: String(p.id) }, { replace: true })} />
              ))}
            </section>
            <VistaDeMiPlan key={`${elegido.id}-${elegido.version}`} plan={elegido} />
            <QueSigue items={queSigue} />
          </>
        )
      })()}
    </div>
  )
}
