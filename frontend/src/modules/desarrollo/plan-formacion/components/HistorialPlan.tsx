import { useState } from 'react'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { ESTADOS_PLAN, type EstadoPlan, type RenglonHistorial } from '../api'
import { useHistorialPlan } from '../hooks/useHistorialPlan'
import { planText } from '../text'

const t = planText.historial

const etiquetaEstado = (valor: string | null): string | null =>
  valor !== null && (ESTADOS_PLAN as string[]).includes(valor) ? planText.estadoPlan[valor as EstadoPlan] : valor

function descripcion(r: RenglonHistorial): string {
  switch (r.tipo) {
    case 'Estado':
      return t.cambio(etiquetaEstado(r.valor_anterior), etiquetaEstado(r.valor_nuevo))
    case 'Participante':
      return r.valor_nuevo !== null ? t.agregado(r.valor_nuevo) : t.quitado(r.valor_anterior ?? planText.noDisponible)
    case 'Aprobador':
      return t.cambio(r.valor_anterior, r.valor_nuevo)
    case 'Cancelacion':
      return planText.estadoAccion.Cancelada
  }
}

/** Historial de un plan: cambios de estado y, desde la aprobación, participantes, cancelaciones y aprobador. */
export function HistorialPlan({ planId }: { planId: number }) {
  const [page, setPage] = useState(1)
  const historial = useHistorialPlan(planId, page)

  return (
    <section aria-labelledby="historial-titulo" className="flex flex-col gap-3">
      <h2 id="historial-titulo" className="text-lg font-bold">
        {t.title}
      </h2>
      {historial.isPending ? (
        <div role="status" aria-label={t.title} className="flex flex-col gap-2">
          {[0, 1, 2].map((fila) => (
            <Skeleton key={fila} className="h-12" />
          ))}
        </div>
      ) : historial.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={planText.error.title} description={planText.error.description} retryLabel={planText.error.retry} onRetry={() => void historial.refetch()} />
        </div>
      ) : historial.data.data.length === 0 ? (
        <p className="rounded-xl border border-dashed border-border bg-surface px-4 py-6 text-center text-muted">{t.vacio}</p>
      ) : (
        <>
          <ol className="flex flex-col gap-2">
            {historial.data.data.map((r) => (
              <li key={r.id} className="rounded-xl border border-border bg-surface p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="rounded-full bg-neutralsoft px-2.5 py-[3px] text-[13px] font-bold text-neutral">{t.tipo[r.tipo]}</span>
                  <span className="text-[15px] font-semibold">{descripcion(r)}</span>
                  {r.accion_nombre && <span className="text-[13px] text-muted">{t.accion(r.accion_nombre)}</span>}
                </div>
                {r.motivo && <p className="mt-1 text-[15px]">{t.motivo(r.motivo)}</p>}
                <p className="mt-1 text-[13px] text-muted">{t.porEn(r.usuario?.nombre ?? planText.noDisponible, planText.fechaHora(r.fecha))}</p>
              </li>
            ))}
          </ol>
          <Pagination
            page={historial.data.meta.page}
            lastPage={historial.data.meta.last_page}
            total={historial.data.meta.total}
            from={(historial.data.meta.page - 1) * historial.data.meta.per_page + 1}
            to={Math.min(historial.data.meta.page * historial.data.meta.per_page, historial.data.meta.total)}
            onPageChange={setPage}
          />
        </>
      )}
    </section>
  )
}
