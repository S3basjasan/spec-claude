import type { InputHTMLAttributes, Ref } from 'react'

interface TextFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  id: string
  label: string
  error?: string
  hint?: string
  ref?: Ref<HTMLInputElement>
}

/** FormField + Input del design system: label arriba, ayuda o error debajo, enlazados por aria. */
export function TextField({ id, label, error, hint, ref, className = '', ...props }: TextFieldProps) {
  const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-semibold">
        {label}
      </label>
      <input
        id={id}
        ref={ref}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy}
        {...props}
        className={`h-11 rounded-lg border bg-surface px-3 text-[15px] text-text placeholder:text-muted ${
          error ? 'border-danger' : 'border-input-border'
        } ${className}`}
      />
      {error ? (
        <p id={`${id}-error`} className="text-[13px] text-danger">
          {error}
        </p>
      ) : hint ? (
        <p id={`${id}-hint`} className="text-[13px] text-muted">
          {hint}
        </p>
      ) : null}
    </div>
  )
}
