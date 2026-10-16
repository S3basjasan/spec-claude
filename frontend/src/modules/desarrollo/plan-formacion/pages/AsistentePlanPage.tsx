import { useNavigate, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { datosDesdePlan, datosVacios } from '../asistente'
import { AsistentePlan } from '../components/AsistentePlan'
import { usePlan } from '../hooks/usePlanes'
import { planText } from '../text'

const t = planText.asistente

/** Crea un plan nuevo (`/plan-formacion/nuevo`) o edita uno en Borrador (`/plan-formacion/:id/editar`). */
export function AsistentePlanPage() {
  const { id } = useParams()
  const navegar = useNavigate()
  const planId = id === undefined ? null : Number(id)
  const plan = usePlan(planId)

  const migas = (
    <Breadcrumb
      label={planId === null ? t.titleNuevo : t.titleEditar}
      items={[{ label: t.breadcrumbInicio, to: '/plan-formacion' }, { label: planId === null ? t.titleNuevo : t.titleEditar }]}
    />
  )

  if (planId !== null && plan.isPending) {
    return (
      <div className="mx-auto flex max-w-275 flex-col gap-6">
        {migas}
        <div role="status" aria-label={t.cargandoPlan} className="flex flex-col gap-2">
          {[0, 1, 2].map((fila) => (
            <Skeleton key={fila} className="h-14" />
          ))}
        </div>
      </div>
    )
  }

  if (planId !== null && plan.isError) {
    const noExiste = toApiError(plan.error).status === 404
    return (
      <div className="mx-auto flex max-w-275 flex-col gap-6">
        {migas}
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState
            title={noExiste ? planText.error.noEncontrado : planText.error.title}
            description={noExiste ? '' : planText.error.description}
            retryLabel={planText.error.retry}
            onRetry={() => void plan.refetch()}
          />
        </div>
      </div>
    )
  }

  const existente = plan.data
  if (existente && !existente.puede.editar) {
    return (
      <div className="mx-auto flex max-w-275 flex-col gap-6">
        {migas}
        <Alert tone="neutral">{t.soloBorrador}</Alert>
      </div>
    )
  }

  const nombresExtra = Object.fromEntries((existente?.acciones ?? []).flatMap((a) => a.competencias.map((c) => [c.id, c.nombre] as const)))

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      {migas}
      <h1 className="text-[28px] font-bold leading-tight tracking-tight">{planId === null ? t.titleNuevo : t.titleEditar}</h1>
      <AsistentePlan
        key={existente?.id ?? 'nuevo'}
        planId={existente?.id}
        inicial={existente ? datosDesdePlan(existente) : datosVacios()}
        nombresExtra={nombresExtra}
        avisosIniciales={existente?.avisos_cruce}
        onTerminar={() => navegar('/plan-formacion')}
      />
    </div>
  )
}
