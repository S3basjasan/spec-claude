import { useEffect, useRef } from 'react'
import { IconPlus, IconX } from './icons'

interface ListaDeCamposProps {
  /** Prefijo de los ids de los campos (`{id}-1`, `{id}-2`…). */
  id: string
  legend: string
  values: string[]
  onChange: (values: string[]) => void
  min?: number
  max?: number
  maxLength?: number
  /** Etiqueta (oculta) de cada campo, p. ej. «Comportamiento 2 del grado A». */
  itemLabel: (posicion: number) => string
  addLabel: string
  removeLabel: (posicion: number) => string
  /** Error de cada campo, por posición. */
  itemErrors?: (string | undefined)[]
  /** Error del grupo (cantidad mínima o máxima). */
  error?: string
  disabled?: boolean
}

/**
 * Campos de texto repetibles (design/DESIGN.md §4): «Agregar» hasta `max` y «Quitar» hasta `min`,
 * con contador `n/máximo` por campo y error por campo.
 */
export function ListaDeCampos({
  id,
  legend,
  values,
  onChange,
  min = 1,
  max = 4,
  maxLength = 300,
  itemLabel,
  addLabel,
  removeLabel,
  itemErrors = [],
  error,
  disabled = false,
}: ListaDeCamposProps) {
  const refs = useRef<(HTMLInputElement | null)[]>([])
  const enfocarUltimo = useRef(false)

  // Tras agregar, el foco pasa al campo nuevo.
  useEffect(() => {
    if (enfocarUltimo.current) {
      enfocarUltimo.current = false
      refs.current[values.length - 1]?.focus()
    }
  }, [values.length])

  const cambiar = (indice: number, texto: string) => onChange(values.map((valor, i) => (i === indice ? texto : valor)))

  return (
    <fieldset className="flex min-w-0 flex-col gap-3 rounded-lg border border-border p-4">
      <legend className="px-1 text-sm font-semibold">{legend}</legend>
      {values.map((valor, indice) => {
        const posicion = indice + 1
        const campoId = `${id}-${posicion}`
        const mensaje = itemErrors[indice]
        return (
          <div key={campoId} className="flex flex-col gap-1.5">
            <label htmlFor={campoId} className="sr-only">
              {itemLabel(posicion)}
            </label>
            <div className="flex items-start gap-2">
              <input
                id={campoId}
                ref={(el) => {
                  refs.current[indice] = el
                }}
                type="text"
                value={valor}
                disabled={disabled}
                maxLength={maxLength}
                aria-invalid={mensaje ? true : undefined}
                aria-describedby={`${campoId}-contador${mensaje ? ` ${campoId}-error` : ''}`}
                onChange={(evento) => cambiar(indice, evento.target.value)}
                className={`h-11 min-w-0 flex-1 rounded-lg border bg-surface px-3 text-[15px] text-text ${
                  mensaje ? 'border-danger' : 'border-input-border'
                }`}
              />
              <button
                type="button"
                aria-label={removeLabel(posicion)}
                disabled={disabled || values.length <= min}
                onClick={() => onChange(values.filter((_, i) => i !== indice))}
                className="flex size-11 shrink-0 items-center justify-center rounded-lg border border-transparent text-text hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45"
              >
                <IconX size={18} />
              </button>
            </div>
            <div className="flex justify-between gap-3 text-[13px]">
              {mensaje ? (
                <p id={`${campoId}-error`} className="text-danger">
                  {mensaje}
                </p>
              ) : (
                <span />
              )}
              <span id={`${campoId}-contador`} className="text-muted">
                {valor.length}/{maxLength}
              </span>
            </div>
          </div>
        )
      })}
      {error && (
        <p role="alert" className="text-[13px] text-danger">
          {error}
        </p>
      )}
      <button
        type="button"
        disabled={disabled || values.length >= max}
        onClick={() => {
          enfocarUltimo.current = true
          onChange([...values, ''])
        }}
        className="inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-lg border border-border bg-surface px-4 text-[15px] font-semibold text-text hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45"
      >
        <IconPlus size={18} />
        {addLabel}
      </button>
    </fieldset>
  )
}
