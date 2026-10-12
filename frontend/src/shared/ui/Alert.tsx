import type { ReactNode } from 'react'
import { IconAlertTriangle, IconInfoCircle } from './icons'

interface AlertProps {
  children: ReactNode
  /** `danger` (por defecto) para errores; `neutral` para avisos informativos (design/DESIGN.md §4). */
  tone?: 'danger' | 'neutral'
}

export function Alert({ children, tone = 'danger' }: AlertProps) {
  if (tone === 'neutral') {
    return (
      <div role="status" className="flex items-start gap-2 rounded-lg border border-border bg-soft px-3.5 py-3 text-sm text-text">
        <IconInfoCircle size={18} className="mt-0.5 shrink-0" />
        <div>{children}</div>
      </div>
    )
  }
  return (
    <div role="alert" className="flex items-start gap-2 rounded-lg bg-dangersoft px-3.5 py-3 text-sm text-danger">
      <IconAlertTriangle size={18} className="mt-0.5 shrink-0" />
      <div>{children}</div>
    </div>
  )
}
