import type { ButtonHTMLAttributes, Ref } from 'react'

type Variant = 'primary' | 'secondary' | 'danger'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  /** Bloquea el botón y lo marca como ocupado mientras hay una operación pendiente. */
  loading?: boolean
  ref?: Ref<HTMLButtonElement>
}

const variants: Record<Variant, string> = {
  primary: 'bg-accent text-onaccent hover:bg-accent-hover border border-transparent',
  secondary: 'bg-surface text-text border border-border hover:bg-soft',
  danger: 'bg-danger text-ondanger border border-transparent',
}

export function Button({ variant = 'primary', loading = false, disabled, className = '', children, ...props }: ButtonProps) {
  return (
    <button
      type="button"
      {...props}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-5 text-[15px] font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${variants[variant]} ${className}`}
    >
      {children}
    </button>
  )
}
