import type { ReactNode } from 'react'

type BadgeVariant = 'default' | 'neutral' | 'warning' | 'danger'

const variants: Record<BadgeVariant, string> = {
  default: 'bg-soft text-accent',
  // Estados inactivos: fondo `--border` y texto `--muted` (design/DESIGN.md §4).
  neutral: 'bg-border text-muted',
  // Advertencia, p. ej. posición Vacante: fondo `--warningsoft` y texto `--warning` (design/DESIGN.md §4).
  warning: 'bg-warningsoft text-warning',
  // Estado Suprimida: fondo `--dangersoft` y texto `--danger` (design/DESIGN.md §4).
  danger: 'bg-dangersoft text-danger',
}

export function Badge({ children, variant = 'default' }: { children: ReactNode; variant?: BadgeVariant }) {
  return (
    <span className={`inline-block rounded-full px-2.5 py-[3px] text-[13px] font-semibold ${variants[variant]}`}>{children}</span>
  )
}
