import { useEffect, useId, useRef, type KeyboardEvent, type ReactNode } from 'react'
import { IconX } from './icons'

interface ModalProps {
  open: boolean
  title: string
  onClose: () => void
  children: ReactNode
  /** Botones de acción, alineados a la derecha. */
  footer?: ReactNode
  /** Ancho máximo del contenido (480 a 640 px, design/DESIGN.md §4). */
  size?: 'md' | 'lg'
  /** Operación en curso: bloquea el cierre. */
  busy?: boolean
  /** Rótulo del botón de cierre de la cabecera. */
  closeLabel?: string
  /** `true` para el modal que se abre encima de otro: queda por delante. */
  nested?: boolean
  /** Descripción accesible del modal (enlaza `aria-describedby`). */
  description?: string
}

const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

/**
 * Modal accesible (design/DESIGN.md §4): `role="dialog"` modal con foco atrapado, Escape que cierra y foco devuelto al
 * control que lo abrió. El foco inicial va al primer campo del cuerpo o, si no hay, al primer control.
 */
export function Modal({ open, title, onClose, children, footer, size = 'md', busy = false, closeLabel = 'Cerrar', nested = false, description }: ModalProps) {
  const titleId = useId()
  const descriptionId = useId()
  const dialogRef = useRef<HTMLDivElement>(null)
  const bodyRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    const previous = document.activeElement instanceof HTMLElement ? document.activeElement : null
    const campo = bodyRef.current?.querySelector<HTMLElement>('input:not([disabled]), select:not([disabled]), textarea:not([disabled])')
    const primero = campo ?? dialogRef.current?.querySelector<HTMLElement>(FOCUSABLE)
    primero?.focus()
    return () => previous?.focus()
  }, [open])

  if (!open) return null

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape' && !busy) {
      event.stopPropagation()
      onClose()
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
    <div
      className={`fixed inset-0 flex items-center justify-center bg-black/40 p-4 ${nested ? 'z-[60]' : 'z-50'}`}
      onKeyDown={onKeyDown}
    >
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descriptionId : undefined}
        className={`flex max-h-full w-full flex-col gap-4 overflow-y-auto rounded-[14px] border border-border bg-surface p-6 shadow-[0_16px_48px_rgba(0,0,0,0.25)] ${
          size === 'lg' ? 'max-w-160' : 'max-w-130'
        }`}
      >
        <div className="flex items-start justify-between gap-3">
          <h2 id={titleId} className="text-lg font-bold">
            {title}
          </h2>
          <button
            type="button"
            aria-label={closeLabel}
            disabled={busy}
            onClick={onClose}
            className="inline-flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-muted hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45"
          >
            <IconX size={18} />
          </button>
        </div>
        {description && (
          <p id={descriptionId} className="text-sm text-muted">
            {description}
          </p>
        )}
        <div ref={bodyRef} className="flex flex-col gap-4">
          {children}
        </div>
        {footer && <div className="flex flex-wrap justify-end gap-3">{footer}</div>}
      </div>
    </div>
  )
}
