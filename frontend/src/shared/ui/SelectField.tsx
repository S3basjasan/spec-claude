import type { Ref, SelectHTMLAttributes } from 'react'

export interface SelectOption {
  value: string
  label: string
}

interface SelectFieldProps extends SelectHTMLAttributes<HTMLSelectElement> {
  id: string
  label: string
  options: SelectOption[]
  error?: string
  /** Oculta el label visualmente (queda para lectores de pantalla), p. ej. en barras de filtros. */
  hideLabel?: boolean
  ref?: Ref<HTMLSelectElement>
}

export function SelectField({ id, label, options, error, hideLabel = false, ref, className = '', ...props }: SelectFieldProps) {
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className={hideLabel ? 'sr-only' : 'text-sm font-semibold'}>
        {label}
      </label>
      <select
        id={id}
        ref={ref}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        {...props}
        className={`h-11 rounded-lg border bg-surface px-3 text-[15px] text-text ${
          error ? 'border-danger' : 'border-input-border'
        } ${className}`}
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      {error && (
        <p id={`${id}-error`} className="text-[13px] text-danger">
          {error}
        </p>
      )}
    </div>
  )
}
