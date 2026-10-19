import { useState } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Modal } from '../../../../shared/ui/Modal'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { IconInfoCircle } from '../../../../shared/ui/icons'
import { useComentarios } from '../hooks/useReportes'
import { fecha, promedio } from '../formato'
import { reportesText } from '../text'

const t = reportesText.comentarios

interface ComentariosDialogProps {
  actividadId: number
  nombre: string
  onCerrar: () => void
}

/** Comentarios de satisfacción de una actividad: promedio, cantidad de respuestas y comentarios anónimos (sin autor ni umbral). */
export function ComentariosDialog({ actividadId, nombre, onCerrar }: ComentariosDialogProps) {
  const [page, setPage] = useState(1)
  const consulta = useComentarios(actividadId, page)
  const meta = consulta.data?.meta
  const datos = consulta.data?.data
  const desde = meta === undefined || meta.total === 0 ? null : (meta.page - 1) * meta.per_page + 1
  const hasta = meta === undefined || meta.total === 0 ? null : Math.min(meta.total, meta.page * meta.per_page)

  return (
    <Modal
      open
      title={t.titulo(nombre)}
      onClose={onCerrar}
      size="lg"
      description={t.descripcion}
      closeLabel={t.cerrar}
      footer={
        <Button variant="secondary" onClick={onCerrar}>
          {t.cerrar}
        </Button>
      }
    >
      {consulta.isPending ? (
        <div role="status" aria-label={reportesText.loading} className="flex flex-col gap-2">
          <Skeleton className="h-10" />
          <Skeleton className="h-16" />
        </div>
      ) : consulta.isError || datos === undefined || meta === undefined ? (
        <ErrorState title={reportesText.error.title} description={reportesText.error.description} retryLabel={reportesText.error.retry} onRetry={() => void consulta.refetch()} />
      ) : (
        <div className="flex flex-col gap-4">
          <dl className="m-0 flex flex-wrap gap-6">
            <div>
              <dt className="text-xs uppercase tracking-wide text-muted">{t.promedio}</dt>
              <dd className="m-0 text-2xl font-bold">{promedio(datos.promedio)}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase tracking-wide text-muted">Respuestas</dt>
              <dd className="m-0 text-2xl font-bold">{datos.respuestas}</dd>
            </div>
          </dl>

          {datos.comentarios.length === 0 ? (
            <EmptyState icon={<IconInfoCircle size={24} />} title={t.vacio} description={t.vacioDescripcion} />
          ) : (
            <>
              <ul className="m-0 flex list-none flex-col gap-2 p-0">
                {datos.comentarios.map((c, i) => (
                  <li key={`${c.fecha}-${i}`} className="rounded-lg border border-border px-3.5 py-3">
                    <div className="flex items-center justify-between gap-3 text-xs text-muted">
                      <span className="font-semibold text-text">{t.calificacion(c.calificacion)}</span>
                      <span>{fecha(c.fecha)}</span>
                    </div>
                    <p className="m-0 mt-1 text-[15px]">{c.comentario}</p>
                  </li>
                ))}
              </ul>
              <Pagination page={meta.page} lastPage={meta.last_page} total={meta.total} from={desde} to={hasta} onPageChange={setPage} />
            </>
          )}
        </div>
      )}
    </Modal>
  )
}
