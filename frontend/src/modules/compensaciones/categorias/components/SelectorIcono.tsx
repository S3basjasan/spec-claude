import type { UseFormRegisterReturn } from 'react-hook-form'
import { ICONOS } from '../iconos'
import { categoriasReconocimientoText } from '../text'
import { IconoCategoria } from './IconoCategoria'

interface SelectorIconoProps {
  /** Registro de react-hook-form del campo `icono` (radios del mismo grupo). */
  registro: UseFormRegisterReturn
  seleccionado: string
  error?: string
  disabled?: boolean
}

/** Selector visual de ícono: los doce de la lista cerrada, como botones de opción con ficha y nombre accesible. */
export function SelectorIcono({ registro, seleccionado, error, disabled = false }: SelectorIconoProps) {
  const errorId = 'categoria-icono-error'

  return (
    <fieldset aria-describedby={error ? errorId : undefined} disabled={disabled} className="m-0 min-w-0 border-0 p-0">
      <legend className="mb-2 p-0 text-sm font-semibold">{categoriasReconocimientoText.form.iconLegend}</legend>
      <div className="flex flex-wrap gap-2">
        {ICONOS.map((icono) => (
          <label key={icono.id} title={icono.nombre} className="relative cursor-pointer">
            <input type="radio" value={icono.id} aria-invalid={error ? true : undefined} className="peer sr-only" {...registro} />
            <span
              className={`inline-flex h-11 w-11 items-center justify-center rounded-lg border text-text transition-colors peer-checked:border-accent peer-checked:bg-soft peer-checked:text-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent ${
                error ? 'border-danger' : 'border-inputborder'
              } ${seleccionado === icono.id ? '' : 'hover:bg-soft'}`}
            >
              <IconoCategoria id={icono.id} />
            </span>
            <span className="sr-only">{icono.nombre}</span>
          </label>
        ))}
      </div>
      {error && (
        <p id={errorId} className="mt-1.5 text-[13px] text-danger">
          {error}
        </p>
      )}
    </fieldset>
  )
}
