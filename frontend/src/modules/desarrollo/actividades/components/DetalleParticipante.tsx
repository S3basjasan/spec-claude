import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { FiltrosCalificaciones } from '../api'
import { useDetalleParticipante } from '../hooks/useActividades'
import { actividadesText } from '../text'
import { BadgeAsistencia } from './BadgeAsistencia'
import { EtiquetaTipo } from './EtiquetaTipo'

const t = actividadesText.calificaciones

interface DetalleParticipanteProps {
  colaboradorId: number
  filtros: Omit<FiltrosCalificaciones, 'agrupar' | 'colaborador_id' | 'page' | 'per_page'>
  onCerrar: () => void
}

/** Detalle por actividad de un participante: asistencia y calificación de cada actividad del filtro. */
export function DetalleParticipante({ colaboradorId, filtros, onCerrar }: DetalleParticipanteProps) {
  const detalle = useDetalleParticipante(colaboradorId, filtros)
  const d = t.detalleColumnas

  return (
    <section aria-label={detalle.data ? t.detalleTitulo(detalle.data.nombre) : t.label} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
      {detalle.isPending ? (
        <div role="status" aria-label={actividadesText.loading}>
          <Skeleton className="h-24" />
        </div>
      ) : detalle.isError ? (
        <ErrorState title={actividadesText.error.title} description={actividadesText.error.description} retryLabel={actividadesText.error.retry} onRetry={() => void detalle.refetch()} />
      ) : (
        <>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h3 className="text-lg font-bold">{t.detalleTitulo(detalle.data.nombre)}</h3>
            <div className="flex items-center gap-3">
              <span className="text-sm text-muted">
                {t.columnasParticipante.promedio}: <strong className="text-text">{actividadesText.promedio(detalle.data.promedio)}</strong>
              </span>
              <Button variant="secondary" onClick={onCerrar}>
                {t.cerrarDetalle}
              </Button>
            </div>
          </div>
          <div className="overflow-x-auto">
            <table aria-label={t.detalleTitulo(detalle.data.nombre)} className="w-full min-w-150 border-collapse text-left">
              <thead>
                <tr className="text-[13px] font-semibold text-muted">
                  <th scope="col" className="px-3 py-2.5">{d.actividad}</th>
                  <th scope="col" className="px-3 py-2.5">{d.fecha}</th>
                  <th scope="col" className="px-3 py-2.5">{d.asistencia}</th>
                  <th scope="col" className="px-3 py-2.5 text-right">{d.calificacion}</th>
                </tr>
              </thead>
              <tbody>
                {detalle.data.actividades.map((a) => (
                  <tr key={a.id} className="border-t border-border text-[15px]">
                    <td className="px-3 py-2.5">
                      <span className="font-semibold">{a.nombre}</span> <EtiquetaTipo tipo={a.tipo} />
                    </td>
                    <td className="whitespace-nowrap px-3 py-2.5">{actividadesText.fecha(a.fecha_inicio)}</td>
                    <td className="px-3 py-2.5">
                      <BadgeAsistencia asistencia={a.asistencia} />
                    </td>
                    <td className="px-3 py-2.5 text-right font-semibold">{a.calificacion ?? t.sinCalificacion}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </section>
  )
}
