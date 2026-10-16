import { useEffect, useId, useRef, type KeyboardEvent } from 'react'
import { Alert } from './Alert'
import { Button } from './Button'

interface ConfirmDialogProps {
  open: boolean
  title: string
  description: string
  confirmLabel: string
  cancelLabel: string
  onConfirm: () => void
  onCancel: () => void
  /** Operación en curso: bloquea los botones y el cierre. */
  busy?: boolean
  /** `danger` (por defecto) para acciones destructivas; `primary` para confirmar una acción que no destruye nada. */
  tone?: 'danger' | 'primary'
  /** Error de la última confirmación; el diálogo sigue abierto. */
  error?: string
}

const FOCUSABLE = 'button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'

/** Confirmación destructiva accesible: alertdialog modal con foco atrapado y devuelto al cerrar. */
export function ConfirmDialog({ open, title, description, confirmLabel, cancelLabel, onConfirm, onCancel, busy = false, tone = 'danger', error }: ConfirmDialogProps) {
  const titleId = useId()
  const descriptionId = useId()
  const dialogRef = useRef<HTMLDivElement>(null)
  const cancelRef = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    if (!open) return
    const previous = document.activeElement instanceof HTMLElement ? document.activeElement : null
    cancelRef.current?.focus()
    return () => previous?.focus()
  }, [open])

  if (!open) return null

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape' && !busy) {
      event.stopPropagation()
      onCancel()
      return
    }
    if (event.key !== 'Tab') return
    const items = Array.from(dialogRef.current?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? [])
    if (items.length === 0) return
    const first = items[0]
    const last = items[items.length - 1]
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first.focus()
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onKeyDown={onKeyDown}>
      <div
        ref={dialogRef}
        role="alertdialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={descriptionId}
        className="flex w-full max-w-95 flex-col gap-4 rounded-xl border border-border bg-surface p-6 shadow-[0_12px_32px_rgba(0,0,0,0.14)]"
      >
        <h2 id={titleId} className="text-lg font-bold">
          {title}
        </h2>
        <p id={descriptionId} className="text-sm text-muted">
          {description}
        </p>
        {error && <Alert>{error}</Alert>}
        <div className="flex justify-end gap-3">
          <Button ref={cancelRef} variant="secondary" onClick={onCancel} disabled={busy}>
            {cancelLabel}
          </Button>
          <Button variant={tone === 'danger' ? 'danger' : 'primary'} onClick={onConfirm} loading={busy}>
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  )
}
