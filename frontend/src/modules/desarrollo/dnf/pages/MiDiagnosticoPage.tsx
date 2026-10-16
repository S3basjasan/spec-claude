import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconChartBar } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { MiDiagnostico } from '../api'
import { BrechasPrioritarias } from '../components/BrechasPrioritarias'
import { KpiDiagnostico } from '../components/KpiDiagnostico'
import { LeyendaBarras } from '../components/LeyendaBarras'
import { useMiDiagnostico } from '../hooks/useMiDiagnostico'
import { dnfText } from '../text'

const text = dnfText.colaborador

function Contenido({ datos }: { datos: MiDiagnostico }) {
  return (
    <>
      <KpiDiagnostico
        items={[
          { label: text.kpi.conBrecha, valor: String(datos.resumen.con_brecha), urgente: datos.resumen.con_brecha > 0 },
          { label: text.kpi.cumples, valor: String(datos.resumen.cumplen) },
          { label: text.kpi.sinEvaluacion, valor: String(datos.resumen.sin_evaluacion) },
          { label: text.kpi.ultimaDnf, valor: datos.dnf.codigo, nota: dnfText.fecha(datos.dnf.fecha) },
        ]}
      />
      {datos.brechas.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconChartBar size={24} />} title={text.vacio.sinBrechas.title} description={text.vacio.sinBrechas.description} />
        </div>
      ) : (
        <LeyendaBarras />
      )}
      <BrechasPrioritarias prioritarias={datos.prioritarias} brechas={datos.brechas} cumplen={datos.cumplen} tieneOferta={datos.tiene_oferta} />
    </>
  )
}

/** Vista del Colaborador: sus brechas de la última DNF, las tres prioritarias y los cursos sugeridos. */
export function MiDiagnosticoPage() {
  const diagnostico = useMiDiagnostico()

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
        <p className="mt-1.5 text-base text-muted">
          {diagnostico.data?.puesto ? text.subtitle(`${diagnostico.data.puesto.nombre} (${diagnostico.data.puesto.codigo})`) : text.subtituloSinPuesto}
        </p>
      </div>

      {diagnostico.isPending ? (
        <div role="status" aria-label={text.loadingLabel} className="grid gap-4 md:grid-cols-3">
          {[0, 1, 2].map((tarjeta) => (
            <Skeleton key={tarjeta} className="h-48" />
          ))}
        </div>
      ) : diagnostico.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState
            title={text.error.title}
            description={text.error.description}
            retryLabel={text.error.retry}
            onRetry={() => void diagnostico.refetch()}
          />
        </div>
      ) : diagnostico.data === null ? (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconChartBar size={24} />} title={text.vacio.sinDnf.title} description={text.vacio.sinDnf.description} />
        </div>
      ) : (
        <Contenido datos={diagnostico.data} />
      )}
    </div>
  )
}
