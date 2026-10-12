import type { InputHTMLAttributes } from 'react'
import { IconSearch } from './icons'

interface SearchFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
  id: string
  label: string
}

/** Búsqueda con ícono de lupa; el label es solo para lectores de pantalla. */
export function SearchField({ id, label, className = '', ...props }: SearchFieldProps) {
  return (
    <div className={`relative ${className}`}>
      <label htmlFor={id} className="sr-only">
        {label}
      </label>
      <IconSearch size={18} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
      <input
        id={id}
        type="search"
        {...props}
        className="h-11 w-full rounded-lg border border-input-border bg-surface pl-9.5 pr-3 text-[15px] text-text placeholder:text-muted"
      />
    </div>
  )
}
