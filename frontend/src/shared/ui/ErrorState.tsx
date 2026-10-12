import { Button } from './Button'
import { IconAlertTriangle } from './icons'

interface ErrorStateProps {
  title: string
  description: string
  retryLabel: string
  onRetry: () => void
}

/** Estado de error con reintento (patrón Estados de design/DESIGN.md). */
export function ErrorState({ title, description, retryLabel, onRetry }: ErrorStateProps) {
  return (
    <div role="alert" className="flex flex-col items-center gap-3 px-4 py-8 text-center">
      <span className="flex size-12 items-center justify-center rounded-full bg-dangersoft text-danger">
        <IconAlertTriangle size={24} />
      </span>
      <h2 className="text-lg font-bold">{title}</h2>
      <p className="max-w-sm text-sm text-muted">{description}</p>
      <Button variant="secondary" onClick={onRetry}>
        {retryLabel}
      </Button>
    </div>
  )
}
