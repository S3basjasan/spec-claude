import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { TextareaField } from '../../../../shared/ui/TextareaField'

interface MotivoDialogProps {
  open: boolean
  title: string
  description: string
  label: string
  /** Mensaje cuando se intenta confirmar sin motivo. */
  requeridoMensaje: string
  confirmLabel: string
  cancelLabel: string
  busy?: boolean
  /** Error devuelto por el servidor al confirmar. */
  error?: string
  onConfirm: (motivo: string) => void
  onCancel: () => void
}

/** Pide un motivo obligatorio (devolver a Borrador, cancelar una acción). */
export function MotivoDialog(props: MotivoDialogProps) {
  // El contenido se monta solo con el modal abierto para partir siempre de un motivo vacío.
  if (!props.open) return null
  return <Contenido {...props} />
}

function Contenido({ title, description, label, requeridoMensaje, confirmLabel, cancelLabel, busy = false, error, onConfirm, onCancel }: MotivoDialogProps) {
  const [motivo, setMotivo] = useState('')
  const [errorLocal, setErrorLocal] = useState<string | undefined>()

  const confirmar = () => {
    if (motivo.trim() === '') {
      setErrorLocal(requeridoMensaje)
      return
    }
    setErrorLocal(undefined)
    onConfirm(motivo.trim())
  }

  return (
    <Modal
      open
      title={title}
      description={description}
      onClose={onCancel}
      busy={busy}
      footer={
        <>
          <Button variant="secondary" onClick={onCancel} disabled={busy}>
            {cancelLabel}
          </Button>
          <Button variant="danger" loading={busy} onClick={confirmar}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-3">
        <p className="text-[15px] text-muted">{description}</p>
        {error && <Alert>{error}</Alert>}
        <TextareaField
          id="motivo-plan"
          label={label}
          value={motivo}
          error={errorLocal}
          maxLength={500}
          currentLength={motivo.length}
          onChange={(e) => setMotivo(e.target.value)}
        />
      </div>
    </Modal>
  )
}
