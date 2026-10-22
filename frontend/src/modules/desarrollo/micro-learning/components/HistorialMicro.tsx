import { useState } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Modal } from '../../../../shared/ui/Modal'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useHistorialMicro, useMicro } from '../hooks/useMicros'
import { microText } from '../text'
import { formatearFecha } from '../tiempo'

interface HistorialMicroProps {
  id: number
  titulo: string
  onCerrar: () => void
}

/** Historial de estados (más reciente primero) y datos de auditoría de creación y última modificación. */
export function HistorialMicro({ id, titulo, onCerrar }: HistorialMicroProps) {
  const t = microText.history
  const [page, setPage] = useState(1)
  const historial = useHistorialMicro(id, page)
  const detalle = useMicro(id)
  const micro = detalle.data

  return (
    <Modal open title={`${t.title}: ${titulo}`} onClose={onCerrar} closeLabel={t.close} footer={<Button variant="secondary" onClick={onCerrar}>{t.close}</Button>}>
      {micro?.created_at && (
        <p className="mb-3 text-[13px] text-muted">
          {t.audit(micro.created_by ?? null, formatearFecha(micro.created_at), micro.updated_by ?? null, formatearFecha(micro.updated_at))}
        </p>
      )}

      {historial.isPending ? (
        <div role="status" aria-label={microText.loadingLabel} className="flex flex-col gap-2">
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
        </div>
      ) : historial.isError ? (
        <ErrorState title={t.error} description={microText.errorDescription} retryLabel={microText.retry} onRetry={() => void historial.refetch()} />
      ) : historial.data.data.length === 0 ? (
        <p className="text-sm text-muted">{t.empty}</p>
      ) : (
        <>
          <ol aria-label={t.title} className="flex flex-col gap-3">
            {historial.data.data.map((entrada) => (
              <li key={entrada.id} className="border-l-2 border-border pl-3">
                <p className="text-[13px] text-muted">
                  <time dateTime={entrada.fecha ?? undefined}>{formatearFecha(entrada.fecha)}</time> {t.by(entrada.usuario)}
                </p>
                <p className="text-[15px] font-semibold">{t.change(entrada.estado_anterior, entrada.estado_nuevo)}</p>
              </li>
            ))}
          </ol>
          {historial.data.meta.last_page > 1 && (
            <div className="mt-4 flex items-center justify-between gap-3">
              <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                {t.previous}
              </Button>
              <span className="text-[13px] text-muted">
                {page} / {historial.data.meta.last_page}
              </span>
              <Button variant="secondary" disabled={page >= historial.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
                {t.next}
              </Button>
            </div>
          )}
        </>
      )}
    </Modal>
  )
}
