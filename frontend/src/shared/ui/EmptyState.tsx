import type { ReactNode } from 'react'

interface EmptyStateProps {
  icon: ReactNode
  title: string
  description: string
  action?: ReactNode
}

/** Estado vacío (patrón Estados de design/DESIGN.md): ícono en círculo suave, título, explicación y acción. */
export function EmptyState({ icon, title, description, action }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center gap-3 px-4 py-10 text-center">
      <span className="flex size-12 items-center justify-center rounded-full bg-soft text-accent">{icon}</span>
      <h2 className="text-lg font-bold">{title}</h2>
      <p className="max-w-sm text-sm text-muted">{description}</p>
      {action}
    </div>
  )
}
