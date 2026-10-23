import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { SearchField } from '../../../../shared/ui/SearchField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { AccionVinculable } from '../api'
import { rangoDeFechas } from '../fecha'
import { useAccionesVinculables } from '../hooks/usePdi'
import { pdiText } from '../text'

const t = pdiText.vinculables

interface SelectorDeAccionVinculableProps {
  open: boolean
  colaboradorId: number | null
  /** Acción del Plan de Formación ya vinculada, si la hay (se marca y se oculta del listado). */
  actual?: number | null
  /** Mensaje de error de la última operación de vínculo. */
  error?: string | null
  busy?: boolean
  onElegir: (accion: AccionVinculable) => void
  onClose: () => void
}

/** Modal con las acciones de planes Aprobados o Ejecutando en las que la persona participa. */
export function SelectorDeAccionVinculable({ open, colaboradorId, actual = null, error = null, busy = false, onElegir, onClose }: SelectorDeAccionVinculableProps) {
  const [texto, setTexto] = useState('')
  const consulta = useAccionesVinculables(colaboradorId, texto, open)
  const acciones = (consulta.data ?? []).filter((a) => a.plan_accion_id !== actual)

  return (
    <Modal open={open} title={t.titulo} description={t.descripcion} onClose={onClose} size="xl" busy={busy} footer={<Button variant="secondary" onClick={onClose} disabled={busy}>{t.cancelar}</Button>}>
      <div className="flex flex-col gap-4">
        <SearchField id="buscar-accion-vinculable" label={t.buscar} value={texto} onChange={(e) => setTexto(e.target.value)} />
        {error !== null && <Alert>{error}</Alert>}

        {consulta.isPending && (
          <div role="status" aria-label={pdiText.loading} className="flex flex-col gap-2">
            <Skeleton className="h-10" />
            <Skeleton className="h-10" />
          </div>
        )}
        {consulta.isError && <Alert>{t.error}</Alert>}
        {consulta.isSuccess && acciones.length === 0 && <p className="text-muted">{t.vacio}</p>}

        {acciones.length > 0 && (
          <ul className="flex max-h-80 flex-col gap-2 overflow-y-auto">
            {acciones.map((a) => (
              <li key={a.plan_accion_id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-3">
                <div className="min-w-0">
                  <p className="m-0 font-semibold">{a.nombre}</p>
                  <p className="m-0 text-[13px] text-muted">
                    {a.plan_codigo} · {a.plan_nombre}
                    {a.tipo !== null && ` · ${a.tipo}`}
                    {a.fecha_inicio !== null && a.fecha_fin !== null && ` · ${rangoDeFechas(a.fecha_inicio, a.fecha_fin)}`}
                  </p>
                </div>
                <Button variant="secondary" onClick={() => onElegir(a)} disabled={busy} aria-label={`${t.elegir}: ${a.nombre}`}>
                  {t.elegir}
                </Button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Modal>
  )
}
