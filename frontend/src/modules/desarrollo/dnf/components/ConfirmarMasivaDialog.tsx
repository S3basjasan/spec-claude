import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { dnfText } from '../text'

const text = dnfText.admin.confirmar

interface ConfirmarMasivaDialogProps {
  open: boolean
  cantidad: number
  departamento: string
  busy: boolean
  error?: string
  onConfirm: () => void
  onCancel: () => void
}

/** Confirmación de la DNF masiva: informa cuántos colaboradores se evaluarán. No es destructiva (botón primario). */
export function ConfirmarMasivaDialog({ open, cantidad, departamento, busy, error, onConfirm, onCancel }: ConfirmarMasivaDialogProps) {
  return (
    <ConfirmDialog
      open={open}
      tone="primary"
      title={text.titulo}
      description={text.descripcion(cantidad, departamento)}
      confirmLabel={text.confirmar}
      cancelLabel={text.cancelar}
      busy={busy}
      error={error}
      onConfirm={onConfirm}
      onCancel={onCancel}
    />
  )
}
