import { CALIFICACION_MAX, CALIFICACION_MIN } from '../api'
import { reportesText } from '../text'

interface EscalaDe5Props {
  /** Prefijo único de los ids de los radios. */
  id: string
  legend: string
  hint?: string
  value: number | null
  error?: string
  onChange: (valor: number) => void
  disabled?: boolean
}

const OPCIONES = Array.from({ length: CALIFICACION_MAX - CALIFICACION_MIN + 1 }, (_, i) => CALIFICACION_MIN + i)

/** Calificación entera de 1 a 5 como grupo de radios accesible (flechas para moverse; el foco no depende del color). */
export function EscalaDe5({ id, legend, hint, value, error, onChange, disabled = false }: EscalaDe5Props) {
  const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined

  return (
    <fieldset aria-describedby={describedBy} aria-invalid={error ? true : undefined} disabled={disabled} className="m-0 flex min-w-0 flex-col gap-1.5 border-0 p-0">
      <legend className="mb-1 p-0 text-sm font-semibold">{legend}</legend>
      <div className="flex flex-wrap gap-2">
        {OPCIONES.map((n) => (
          <label
            key={n}
            htmlFor={`${id}-${n}`}
            className={`flex size-11 cursor-pointer items-center justify-center rounded-lg border text-base font-bold focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent ${
              value === n ? 'border-accent bg-accent text-onaccent' : 'border-input-border bg-surface text-text hover:bg-soft'
            }`}
          >
            <input id={`${id}-${n}`} type="radio" name={id} value={n} checked={value === n} onChange={() => onChange(n)} className="sr-only" aria-label={reportesText.formularioEncuesta.opcion(n)} />
            <span aria-hidden="true">{n}</span>
          </label>
        ))}
      </div>
      {hint && !error && (
        <p id={`${id}-hint`} className="m-0 text-[13px] text-muted">
          {hint}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} role="alert" className="m-0 text-[13px] text-danger">
          {error}
        </p>
      )}
    </fieldset>
  )
}
