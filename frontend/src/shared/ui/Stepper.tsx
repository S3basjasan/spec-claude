interface StepperProps {
  /** Etiqueta accesible de la navegación por pasos. */
  label: string
  pasos: string[]
  /** Índice (desde 0) del paso actual. */
  actual: number
  /** Último índice al que se puede volver o avanzar; los posteriores quedan deshabilitados. */
  maximo: number
  onSelect: (indice: number) => void
}

/** Pasos numerados (design/DESIGN.md §4): el actual lleva `aria-current="step"` y el no alcanzable va deshabilitado. */
export function Stepper({ label, pasos, actual, maximo, onSelect }: StepperProps) {
  return (
    <nav aria-label={label}>
      <ol className="flex flex-wrap gap-3">
        {pasos.map((paso, indice) => (
          <li key={paso}>
            <button
              type="button"
              aria-current={indice === actual ? 'step' : undefined}
              disabled={indice > maximo}
              onClick={() => onSelect(indice)}
              className={`flex min-h-11 items-center gap-2.5 rounded-lg border px-4 text-[15px] font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-55 ${
                indice === actual ? 'border-accent bg-soft text-text' : 'border-border bg-surface text-muted hover:bg-soft'
              }`}
            >
              <span
                aria-hidden="true"
                className={`flex size-6 items-center justify-center rounded-full text-[13px] ${
                  indice === actual ? 'bg-accent text-onaccent' : 'bg-border text-muted'
                }`}
              >
                {indice + 1}
              </span>
              {paso}
            </button>
          </li>
        ))}
      </ol>
    </nav>
  )
}
