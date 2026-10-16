import { Link, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { AvisosDnf } from '../components/AvisosDnf'
import { BotonGenerarPlan } from '../components/BotonGenerarPlan'
import { KpiDiagnostico } from '../components/KpiDiagnostico'
import { PanelDiagnostico } from '../components/PanelDiagnostico'
import { ResumenPorColaborador } from '../components/ResumenPorColaborador'
import { useFiltrosDiagnosticoUrl } from '../hooks/useDnfParams'
import { useDetalleDnf } from '../hooks/useDetalleDnf'
import { dnfText } from '../text'

const text = dnfText.detalle
const admin = dnfText.admin

/** Detalle de una DNF en solo lectura: el mismo diagnóstico del asistente, sin acciones de edición. */
export function DnfDetallePage() {
  const { id } = useParams()
  const dnfId = Number.isInteger(Number(id)) && Number(id) > 0 ? Number(id) : null
  const { filtros, colaborador, cambiarFiltros, elegirColaborador } = useFiltrosDiagnosticoUrl()
  const detalle = useDetalleDnf(dnfId, colaborador)

  const migas = [{ label: dnfText.historial.title, to: '/dnf/historial' }, { label: detalle.data?.data.codigo ?? '…' }]

  if (dnfId === null || (detalle.isError && toApiError(detalle.error).status === 404)) {
    return (
      <div role="alert" className="mx-auto flex max-w-md flex-col items-center gap-3 py-12 text-center">
        <h1 className="text-xl font-bold">{text.noExiste.title}</h1>
        <p className="text-sm text-muted">{text.noExiste.description}</p>
        <Link to="/dnf/historial" className="font-semibold text-accent underline">
          {text.noExiste.back}
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      <Breadcrumb label={dnfText.historial.title} items={migas} />

      {detalle.isPending ? (
        <div role="status" aria-label={dnfText.historial.tableLabel} className="flex flex-col gap-3">
          <Skeleton className="h-10" />
          <Skeleton className="h-64" />
        </div>
      ) : detalle.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState
            title={admin.error.title}
            description={admin.error.description}
            retryLabel={admin.error.retry}
            onRetry={() => void detalle.refetch()}
          />
        </div>
      ) : (
        <Contenido
          dnf={detalle.data.data}
          avisos={detalle.data.avisos}
          filtros={filtros}
          colaborador={colaborador}
          onFiltros={cambiarFiltros}
          onElegir={elegirColaborador}
        />
      )}
    </div>
  )
}

interface ContenidoProps {
  dnf: NonNullable<ReturnType<typeof useDetalleDnf>['data']>['data']
  avisos: NonNullable<ReturnType<typeof useDetalleDnf>['data']>['avisos']
  filtros: ReturnType<typeof useFiltrosDiagnosticoUrl>['filtros']
  colaborador: number | null
  onFiltros: ReturnType<typeof useFiltrosDiagnosticoUrl>['cambiarFiltros']
  onElegir: ReturnType<typeof useFiltrosDiagnosticoUrl>['elegirColaborador']
}

function Contenido({ dnf, avisos, filtros, colaborador, onFiltros, onElegir }: ContenidoProps) {
  const masiva = dnf.alcance === 'Departamento'
  const resultado = dnf.colaboradores.find((c) => c.colaborador_id === colaborador) ?? (masiva ? undefined : dnf.colaboradores[0])
  const objetivo = masiva ? (dnf.departamento?.nombre ?? dnfText.noDisponible) : (dnf.colaboradores[0]?.nombre ?? dnfText.noDisponible)

  return (
    <>
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">
          {dnf.codigo} · {objetivo}
        </h1>
        <p className="mt-1.5 text-base text-muted">
          {dnfText.alcance[dnf.alcance]} · {dnfText.fecha(dnf.fecha)}
          {dnf.generado_por ? ` · ${text.generadoPor(dnf.generado_por.nombre, dnfText.fecha(dnf.fecha))}` : ''}
        </p>
        <p className="mt-1 text-sm text-muted">{text.solo_lectura}</p>
      </div>

      {avisos && <AvisosDnf avisos={avisos} />}

      <BotonGenerarPlan dnfId={dnf.id} cantidadBrechas={dnf.resumen.cantidad_brechas} plan={dnf.plan_formacion} />

      {masiva && colaborador === null && (
        <>
          <KpiDiagnostico
            items={[
              { label: admin.kpi.colaboradores, valor: String(dnf.resumen.colaboradores) },
              { label: admin.kpi.conBrecha, valor: String(dnf.resumen.cantidad_brechas) },
              { label: admin.kpi.prioridadMasAlta, valor: dnf.resumen.prioridad_mas_alta ? dnfText.prioridad[dnf.resumen.prioridad_mas_alta] : dnfText.sinPrioridad },
            ]}
          />
          <ResumenPorColaborador colaboradores={dnf.colaboradores} onElegir={onElegir} />
        </>
      )}

      {masiva && colaborador !== null && (
        <div className="flex flex-col gap-4">
          <div>
            <Button variant="secondary" onClick={() => onElegir(null)}>
              {admin.paso2.volverResumen}
            </Button>
          </div>
          {resultado?.brechas ? (
            <PanelDiagnostico nombreColaborador={resultado.nombre} brechas={resultado.brechas} filtros={filtros} onFiltros={onFiltros} />
          ) : null}
        </div>
      )}

      {!masiva && resultado?.brechas && (
        <PanelDiagnostico nombreColaborador={resultado.nombre} brechas={resultado.brechas} filtros={filtros} onFiltros={onFiltros} />
      )}
    </>
  )
}
