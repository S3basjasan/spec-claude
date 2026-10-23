import { Link, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconArrowLeft, IconFileText } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { DIMENSIONES_EN_ORDEN } from '../api'
import { AccionesDeAdecuacion } from '../components/AccionesDeAdecuacion'
import { PreparacionBadge } from '../components/Etiquetas'
import { LeyendaDeBarras } from '../components/LeyendaDeBarras'
import { PanelDeDimension } from '../components/PanelDeDimension'
import { useAdecuacionDetalle } from '../hooks/useEvaluaciones'
import { adecuacionText } from '../text'

const t = adecuacionText
const d = adecuacionText.detalle

/** Detalle de solo lectura de una adecuación: los tres paneles, la auditoría y las acciones (deshabilitadas con su motivo). */
export function DetalleAdecuacionPage() {
  const { id } = useParams()
  const numero = Number(id)
  const consulta = useAdecuacionDetalle(Number.isInteger(numero) && numero > 0 ? numero : null)
  const adecuacion = consulta.data

  const volver = (
    <Link to="/adecuacion?pestana=evaluaciones" className="inline-flex min-h-11 items-center gap-1.5 font-semibold text-accent no-underline hover:underline">
      <IconArrowLeft size={18} />
      {d.volver}
    </Link>
  )

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={t.loading} className="mx-auto flex max-w-[1200px] flex-col gap-3">
        <Skeleton className="h-10 w-80" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (consulta.isError || adecuacion === undefined) {
    const noExiste = toApiError(consulta.error).status === 404

    return (
      <div className="mx-auto flex max-w-[1200px] flex-col gap-4">
        {volver}
        {noExiste ? (
          <EmptyState icon={<IconFileText />} title={d.noEncontrada.title} description={d.noEncontrada.description} />
        ) : (
          <ErrorState title={t.errores.cargar.title} description={t.errores.cargar.description} retryLabel={t.reintentar} onRetry={() => void consulta.refetch()} />
        )}
      </div>
    )
  }

  return (
    <div className="mx-auto flex max-w-[1200px] flex-col gap-5">
      {volver}
      <header>
        <p className="m-0 text-[13px] font-semibold text-muted">{t.sobretitulo}</p>
        <h1 className="m-0 mt-0.5 text-2xl font-bold">{d.titulo(adecuacion.codigo)}</h1>
        <p className="m-0 mt-1 text-muted">{d.auditoria(adecuacion.generada_por, adecuacion.fecha)}</p>
      </header>

      {/* <p role="note" className="m-0 rounded-xl border border-border bg-infosoft px-4 py-3 text-sm text-info">
        {t.avisoGuia} {d.soloLectura}
      </p> */}

      <section aria-label={t.resumen.etiqueta} className="flex flex-wrap items-center justify-between gap-x-8 gap-y-4 rounded-xl border border-border bg-surface px-5 py-[18px]">
        <div className="min-w-0">
          <div className="text-lg font-bold">
            {adecuacion.puesto_actual?.nombre ?? t.resumen.sinPuestoActual} → {adecuacion.puesto_objetivo.nombre}
          </div>
          <div className="mt-0.5 text-[13px] text-muted">{t.resumen.area(adecuacion.colaborador.nombre, adecuacion.colaborador.departamento, null)}</div>
          <div className="mt-0.5 text-[13px] text-muted">{d.umbral(adecuacion.umbral_meses)}</div>
          <div className="mt-0.5 text-[13px] text-muted">{adecuacion.es_vigente ? d.vigente : d.reemplazada}</div>
        </div>
        <div>
          <div className="mb-1 text-[13px] text-muted">{t.resumen.preparacion}</div>
          <PreparacionBadge valor={adecuacion.preparacion} />
        </div>
      </section>

      <LeyendaDeBarras />

      <div className="grid items-start gap-4 [grid-template-columns:repeat(auto-fit,minmax(min(100%,330px),1fr))]">
        {DIMENSIONES_EN_ORDEN.map((dimension) => (
          <PanelDeDimension
            key={dimension}
            dimension={dimension}
            resultados={adecuacion.resultados.filter((r) => r.dimension === dimension)}
            cumplimientoActual={adecuacion.cumplimiento_por_dimension_actual[dimension]}
            cumplimientoObjetivo={adecuacion.cumplimiento_por_dimension[dimension]}
            motivoSinFilas={dimension === 'Conocimiento' ? 'desactivada' : dimension === 'Experiencia' ? 'noEvaluada' : null}
          />
        ))}
      </div>

      {adecuacion.observaciones !== null && (
        <section aria-labelledby="detalle-obs" className="rounded-xl border border-border bg-surface px-5 py-4">
          <h2 id="detalle-obs" className="m-0 text-lg font-semibold">{d.observaciones}</h2>
          <p className="m-0 mt-2 whitespace-pre-line text-sm">{adecuacion.observaciones}</p>
        </section>
      )}

      <AccionesDeAdecuacion
        conBrechas={adecuacion.brechas > 0}
        motivo={adecuacion.acciones.crear_plan.motivo}
        plan={{
          colaboradorId: adecuacion.colaborador.id,
          puestoObjetivoId: adecuacion.puesto_objetivo.id,
          crear: adecuacion.acciones.crear_plan,
          incluir: adecuacion.acciones.incluir_programa,
        }}
      />
    </div>
  )
}
