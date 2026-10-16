import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import type { Accion } from '../api'
import { useCancelarAccion } from '../hooks/useCambiarParticipantes'
import { planText } from '../text'
import { MotivoDialog } from './MotivoDialog'

const t = planText.cancelar

interface CancelarAccionDialogProps {
  /** Acción a cancelar; `null` cierra el diálogo. */
  accion: Accion | null
  onClose: () => void
}

/** Cancela una acción con motivo obligatorio e informa que sus participantes dejarán de verla como pendiente. */
export function CancelarAccionDialog({ accion, onClose }: CancelarAccionDialogProps) {
  const cancelar = useCancelarAccion()
  const [error, setError] = useState<string | undefined>()

  const cerrar = () => {
    setError(undefined)
    onClose()
  }

  return (
    <MotivoDialog
      open={accion !== null}
      title={accion ? `${t.title}: ${accion.nombre}` : t.title}
      description={t.description}
      label={t.motivo}
      requeridoMensaje={t.motivoRequerido}
      confirmLabel={t.confirmar}
      cancelLabel={t.volver}
      busy={cancelar.isPending}
      error={error}
      onCancel={cerrar}
      onConfirm={async (motivo) => {
        if (!accion) return
        setError(undefined)
        try {
          await cancelar.mutateAsync({ accionId: accion.id, motivo })
          cerrar()
        } catch (e) {
          const api = toApiError(e)
          setError(t.errores[api.code ?? ''] ?? api.message ?? planText.error.generico)
        }
      }}
    />
  )
}
