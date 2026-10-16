import { useState } from 'react'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { AccionMiPlan } from '../api'
import { DetalleMiAccion } from '../components/DetalleMiAccion'
import { ProgresoDelPlan } from '../components/ProgresoDelPlan'
import { ProximasActividades } from '../components/ProximasActividades'
import { TarjetaAccion } from '../components/TarjetaAccion'
import { useMiPlan } from '../hooks/useMiPlan'
import { planText } from '../text'

const t = planText.miPlan

/** «Mi plan de formación» (Colaborador): sus acciones en orden cronológico, el % completado y las próximas actividades. */
export function MiPlanPage() {
  const miPlan = useMiPlan()
  const [seleccionada, setSeleccionada] = useState<AccionMiPlan | null>(null)

  const anio = miPlan.data && miPlan.data.acciones.length > 0 ? Math.max(...miPlan.data.acciones.map((a) => a.plan.anio)) : null

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{t.title}</h1>
        <p className="mt-1.5 text-base text-muted">{t.subtitle(anio)}</p>
      </div>

      {miPlan.isPending ? (
        <div role="status" aria-label={t.loading} className="flex flex-col gap-2">
          {[0, 1, 2].map((fila) => (
            <Skeleton key={fila} className="h-20" />
          ))}
        </div>
      ) : miPlan.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={planText.error.title} description={planText.error.description} retryLabel={planText.error.retry} onRetry={() => void miPlan.refetch()} />
        </div>
      ) : miPlan.data.acciones.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconFileText size={24} />} title={t.vacio.title} description={t.vacio.description} />
        </div>
      ) : (
        <>
          <ProgresoDelPlan porcentaje={miPlan.data.porcentaje_completado} completadas={miPlan.data.acciones_completadas} asignadas={miPlan.data.acciones_asignadas} />
          <ProximasActividades acciones={miPlan.data.proximas} onVerDetalle={setSeleccionada} />
          <section aria-labelledby="todas-titulo" className="flex flex-col gap-3">
            <h2 id="todas-titulo" className="text-lg font-bold">
              {t.todas}
            </h2>
            {miPlan.data.acciones.map((accion) => (
              <TarjetaAccion key={accion.id} accion={accion} onVerDetalle={setSeleccionada} />
            ))}
          </section>
        </>
      )}

      <DetalleMiAccion accion={seleccionada} onClose={() => setSeleccionada(null)} />
    </div>
  )
}
