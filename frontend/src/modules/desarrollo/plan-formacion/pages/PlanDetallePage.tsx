import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { Accion } from '../api'
import { AccionesPlan } from '../components/AccionesPlan'
import { BadgeEstadoPlan } from '../components/BadgeEstadoPlan'
import { CancelarAccionDialog } from '../components/CancelarAccionDialog'
import { DetallePlan } from '../components/DetallePlan'
import { EditorParticipantes } from '../components/EditorParticipantes'
import { HistorialPlan } from '../components/HistorialPlan'
import { usePlan } from '../hooks/usePlanes'
import { planText } from '../text'

/** Detalle de un plan (Administrador): datos, totales, acciones y, según el estado, los botones del ciclo de vida. */
export function PlanDetallePage() {
  const { id } = useParams()
  const planId = Number(id)
  const plan = usePlan(Number.isInteger(planId) && planId > 0 ? planId : null)
  const [editando, setEditando] = useState<Accion | null>(null)
  const [cancelando, setCancelando] = useState<Accion | null>(null)

  const migas = (nombre: string) => (
    <Breadcrumb label={nombre} items={[{ label: planText.detalle.breadcrumbInicio, to: '/plan-formacion' }, { label: nombre }]} />
  )

  if (plan.isPending) {
    return (
      <div className="mx-auto flex max-w-275 flex-col gap-6">
        {migas(planText.planes.title)}
        <div role="status" aria-label={planText.asistente.cargandoPlan} className="flex flex-col gap-2">
          {[0, 1, 2].map((fila) => (
            <Skeleton key={fila} className="h-14" />
          ))}
        </div>
      </div>
    )
  }

  if (plan.isError) {
    const noExiste = toApiError(plan.error).status === 404
    return (
      <div className="mx-auto flex max-w-275 flex-col gap-6">
        {migas(planText.planes.title)}
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

  const datos = plan.data
  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      {migas(datos.nombre)}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{datos.nombre}</h1>
          <p className="mt-1.5 flex items-center gap-2 text-base text-muted">
            {datos.codigo} · <BadgeEstadoPlan estado={datos.estado} />
          </p>
        </div>
        <AccionesPlan planId={datos.id} nombre={datos.nombre} puede={datos.puede} modo="detalle" aprobadorId={datos.aprobador?.id ?? null} />
      </div>
      <DetallePlan
        plan={datos}
        accionesDe={(accion) =>
          accion.cancelada ? null : (
            <div className="flex flex-wrap gap-2">
              {datos.puede.cambiar_participantes && (
                <Button variant="secondary" aria-label={`${planText.botones.cambiarParticipantes}: ${accion.nombre}`} onClick={() => setEditando(accion)}>
                  {planText.botones.cambiarParticipantes}
                </Button>
              )}
              {datos.puede.cancelar_acciones && (
                <Button variant="secondary" aria-label={`${planText.botones.cancelarAccion}: ${accion.nombre}`} onClick={() => setCancelando(accion)}>
                  {planText.botones.cancelarAccion}
                </Button>
              )}
            </div>
          )
        }
      />
      <HistorialPlan planId={datos.id} />
      <EditorParticipantes key={editando?.id ?? 'ninguna'} accion={editando} onClose={() => setEditando(null)} />
      <CancelarAccionDialog accion={cancelando} onClose={() => setCancelando(null)} />
    </div>
  )
}
