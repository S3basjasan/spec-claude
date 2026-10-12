import type { Ref, TextareaHTMLAttributes } from 'react'

interface TextareaFieldProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  id: string
  label: string
  error?: string
  hint?: string
  /** Si se indican ambos, muestra el contador "n/máximo" bajo el campo. */
  currentLength?: number
  maxLength?: number
  ref?: Ref<HTMLTextAreaElement>
}

export function TextareaField({ id, label, error, hint, currentLength, maxLength, ref, className = '', ...props }: TextareaFieldProps) {
  const describedBy = [error ? `${id}-error` : hint ? `${id}-hint` : null, maxLength !== undefined ? `${id}-count` : null]
    .filter(Boolean)
    .join(' ')
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-semibold">
        {label}
      </label>
      <textarea
        id={id}
        ref={ref}
        rows={3}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy || undefined}
        {...props}
        className={`rounded-lg border bg-surface px-3 py-2.5 text-[15px] text-text placeholder:text-muted ${
          error ? 'border-danger' : 'border-input-border'
        } ${className}`}
      />
      <div className="flex justify-between gap-3">
        <div>
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
        {maxLength !== undefined && currentLength !== undefined && (
          <p id={`${id}-count`} className="shrink-0 text-[13px] text-muted">
            {currentLength}/{maxLength}
          </p>
        )}
      </div>
    </div>
  )
}
