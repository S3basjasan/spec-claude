import { Alert } from '../../../../shared/ui/Alert'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconChartBar } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { GraficoEvolucionCompetencias } from '../components/GraficoEvolucionCompetencias'
import { ListaCertificados } from '../components/ListaCertificados'
import { PendientesPorResponder } from '../components/PendientesPorResponder'
import { PlanDeDesarrollo } from '../components/PlanDeDesarrollo'
import { TarjetasProgreso } from '../components/TarjetasProgreso'
import { useMiProgreso } from '../hooks/useReportes'
import { reportesText } from '../text'

const t = reportesText.colaborador

/** «Mi progreso» (Colaborador): tarjetas de resumen, evolución de competencias, certificados, plan de desarrollo y pendientes. */
export function MiProgresoPage() {
  const progreso = useMiProgreso()
  const d = progreso.data
  const sinDatos =
    d !== undefined &&
    d.resumen.formaciones_completadas === 0 &&
    d.resumen.certificados === 0 &&
    d.evolucion_competencias.length === 0 &&
    d.proximas_acciones.length === 0 &&
    d.pendientes.encuestas.length === 0 &&
    d.pendientes.seguimientos.length === 0

  return (
    <div className="mx-auto flex max-w-300 flex-col gap-6">
      <div>
        <h1 className="m-0 text-[28px] font-bold leading-tight tracking-tight">{t.title}</h1>
        <p className="mt-1.5 text-base text-muted">{t.subtitle}</p>
      </div>

      {progreso.isPending ? (
        <div role="status" aria-label={reportesText.loading} className="flex flex-col gap-3">
          <Skeleton className="h-28" />
          <Skeleton className="h-48" />
        </div>
      ) : progreso.isError || d === undefined ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={reportesText.error.title} description={reportesText.error.description} retryLabel={reportesText.error.retry} onRetry={() => void progreso.refetch()} />
        </div>
      ) : sinDatos ? (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconChartBar size={24} />} title={t.vacio.titulo} description={t.vacio.descripcion} />
        </div>
      ) : (
        <>
          {d.fuentes_no_disponibles.length > 0 && (
            <Alert tone="neutral">
              <strong>{reportesText.fuentesCaidas.titulo(d.fuentes_no_disponibles)}</strong> {reportesText.fuentesCaidas.descripcion}
            </Alert>
          )}
          <TarjetasProgreso resumen={d.resumen} />
          <GraficoEvolucionCompetencias filas={d.evolucion_competencias} />
          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <ListaCertificados certificados={d.certificados} />
            <PlanDeDesarrollo acciones={d.proximas_acciones} />
          </div>
          <PendientesPorResponder pendientes={d.pendientes} />
        </>
      )}
    </div>
  )
}
