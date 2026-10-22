import { useEffect, useId, useRef, type KeyboardEvent } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { IconCheck } from '../../../../shared/ui/icons'
import { competenciasText } from '../text'

interface AvisoEmergenteProps {
  /** Mensaje del resultado; sin mensaje el aviso no se muestra. */
  mensaje?: string
  onClose: () => void
}

/**
 * Aviso emergente centrado con el resultado de una acción: `alertdialog` modal con el foco inicial en Aceptar,
 * que se cierra con Aceptar o con Escape (design/DESIGN.md, listado de tarjetas con pestañas).
 */
export function AvisoEmergente({ mensaje, onClose }: AvisoEmergenteProps) {
  const titleId = useId()
  const aceptarRef = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    if (!mensaje) return
    const previo = document.activeElement instanceof HTMLElement ? document.activeElement : null
    aceptarRef.current?.focus()
    return () => previo?.focus()
  }, [mensaje])

  if (!mensaje) return null

  const alTeclear = (evento: KeyboardEvent<HTMLDivElement>) => {
    if (evento.key === 'Escape') {
      evento.stopPropagation()
      onClose()
    } else if (evento.key === 'Tab') {
      // El único control es Aceptar: el foco se queda dentro del aviso.
      evento.preventDefault()
      aceptarRef.current?.focus()
    }
  }

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4" onKeyDown={alTeclear}>
      <div
        role="alertdialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className="flex w-full max-w-100 flex-col items-center gap-3 rounded-[14px] border border-border bg-surface p-6 text-center shadow-[0_16px_48px_rgba(0,0,0,0.25)]"
      >
        <span aria-hidden="true" className="flex size-12 items-center justify-center rounded-full bg-soft text-accent">
          <IconCheck size={24} />
        </span>
        <h2 id={titleId} className="text-lg font-bold">
          {mensaje}
        </h2>
        <Button ref={aceptarRef} className="mt-2 px-7" onClick={onClose}>
          {competenciasText.notices.accept}
        </Button>
      </div>
    </div>
  )
}
