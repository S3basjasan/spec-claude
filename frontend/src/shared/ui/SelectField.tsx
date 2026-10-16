import type { ReactElement, Ref, SelectHTMLAttributes } from 'react'

export interface SelectOption {
  value: string
  label: string
  /** La opción se muestra pero no puede elegirse (p. ej. "Ninguna (unidad raíz)" cuando ya existe una raíz). */
  disabled?: boolean
  /** Agrupa las opciones consecutivas con el mismo grupo en un `<optgroup>`; sin grupo, la opción va suelta. */
  group?: string
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

function renderOption(option: SelectOption): ReactElement {
  return (
    <option key={option.value} value={option.value} disabled={option.disabled}>
      {option.label}
    </option>
  )
}

function renderOptions(options: SelectOption[]): ReactElement[] {
  const secciones: { grupo?: string; items: SelectOption[] }[] = []
  for (const option of options) {
    const ultima = secciones[secciones.length - 1]
    if (option.group !== undefined && ultima?.grupo === option.group) ultima.items.push(option)
    else secciones.push({ grupo: option.group, items: [option] })
  }

  return secciones.flatMap((seccion, indice) =>
    seccion.grupo === undefined
      ? seccion.items.map(renderOption)
      : [
          <optgroup key={`grupo-${indice}`} label={seccion.grupo}>
            {seccion.items.map(renderOption)}
          </optgroup>,
        ],
  )
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
        className={`h-11 rounded-lg border bg-surface px-3 text-[15px] text-text disabled:cursor-not-allowed disabled:bg-bg disabled:text-muted ${
          error ? 'border-danger' : 'border-input-border'
        } ${className}`}
      >
        {renderOptions(options)}
      </select>
      {error && (
        <p id={`${id}-error`} className="text-[13px] text-danger">
          {error}
        </p>
      )}
    </div>
  )
}
