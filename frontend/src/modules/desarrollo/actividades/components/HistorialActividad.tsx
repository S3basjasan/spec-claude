import { useState } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Modal } from '../../../../shared/ui/Modal'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { Asistencia, EntradaHistorial } from '../api'
import { useHistorialActividad } from '../hooks/useActividades'
import { actividadesText } from '../text'

const t = actividadesText.historial
const c = t.columnas

function valor(asistencia: Asistencia | null, calificacion: number | null): string {
  const base = asistencia === null ? t.pendiente : actividadesText.asistencia[asistencia]
  return calificacion === null ? base : `${base} (${calificacion})`
}

function cambioDe(e: EntradaHistorial): string {
  return t.cambio(valor(e.asistencia_anterior, e.calificacion_anterior), valor(e.asistencia_nueva, e.calificacion_nueva))
}

function fechaHora(iso: string | null): string {
  if (!iso) return actividadesText.sinDato
  const fecha = new Date(iso)
  return Number.isNaN(fecha.getTime()) ? actividadesText.sinDato : fecha.toLocaleString('es-EC', { dateStyle: 'medium', timeStyle: 'short' })
}

/** Historial de registros de una actividad: usuario, fecha, participante, valor anterior y valor nuevo. */
export function HistorialActividad({ actividadId, onCerrar }: { actividadId: number; onCerrar: () => void }) {
  const [pagina, setPagina] = useState(1)
  const historial = useHistorialActividad(actividadId, pagina)
  const meta = historial.data?.meta

  return (
    <Modal open title={t.title} onClose={onCerrar} size="lg" closeLabel={t.cerrar} footer={<Button variant="secondary" onClick={onCerrar}>{t.cerrar}</Button>}>
      {historial.isPending ? (
        <div role="status" aria-label={actividadesText.loading} className="flex flex-col gap-2">
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
        </div>
      ) : historial.isError ? (
        <ErrorState title={actividadesText.error.title} description={actividadesText.error.description} retryLabel={actividadesText.error.retry} onRetry={() => void historial.refetch()} />
      ) : historial.data.data.length === 0 ? (
        <p className="py-6 text-center text-muted">{t.vacio}</p>
      ) : (
        <div className="flex flex-col gap-3">
          <div className="overflow-x-auto">
            <table aria-label={t.tableLabel} className="w-full min-w-150 border-collapse text-left">
              <thead>
                <tr className="text-[13px] font-semibold text-muted">
                  <th scope="col" className="px-3 py-2.5">{c.fecha}</th>
                  <th scope="col" className="px-3 py-2.5">{c.usuario}</th>
                  <th scope="col" className="px-3 py-2.5">{c.participante}</th>
                  <th scope="col" className="px-3 py-2.5">{c.asistencia}</th>
                </tr>
              </thead>
              <tbody>
                {historial.data.data.map((e) => (
                  <tr key={e.id} className="border-t border-border text-[15px]">
                    <td className="whitespace-nowrap px-3 py-2.5">{fechaHora(e.fecha)}</td>
                    <td className="px-3 py-2.5">{e.usuario}</td>
                    <td className="px-3 py-2.5">{e.participante}</td>
                    <td className="px-3 py-2.5">{cambioDe(e)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {meta && meta.last_page > 1 && (
            <nav aria-label={t.title} className="flex items-center justify-between gap-3 text-sm text-muted">
              <Button variant="secondary" disabled={pagina <= 1} onClick={() => setPagina(pagina - 1)}>
                {t.anterior}
              </Button>
              <span>{t.pagina(meta.page, meta.last_page)}</span>
              <Button variant="secondary" disabled={pagina >= meta.last_page} onClick={() => setPagina(pagina + 1)}>
                {t.siguiente}
              </Button>
            </nav>
          )}
        </div>
      )}
    </Modal>
  )
}
