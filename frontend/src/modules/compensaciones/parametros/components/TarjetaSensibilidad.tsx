import type { UseFormRegisterReturn } from 'react-hook-form'
import type { Parametro } from '../api'
import { parametrosText } from '../text'
import { UltimoCambioDeParametro } from './UltimoCambioDeParametro'

interface TarjetaSensibilidadProps {
  parametro: Parametro
  registro: UseFormRegisterReturn
  error?: string
}

const text = parametrosText.sensibilidad
const OPCIONES = [1, 2, 3] as const

/** Sensibilidad del radar de clima: tres opciones de radio con su explicación; nunca se escriben umbrales. */
export function TarjetaSensibilidad({ parametro, registro, error }: TarjetaSensibilidadProps) {
  return (
    <section aria-labelledby="parametro-sensibilidad-titulo" className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
      <div>
        <h2 id="parametro-sensibilidad-titulo" className="text-lg font-semibold">
          {text.title}
        </h2>
        <p className="mt-1 text-sm text-muted">{text.description}</p>
      </div>

      <fieldset className="flex flex-col gap-2" aria-describedby={error ? 'parametro-sensibilidad-error' : undefined}>
        <legend className="sr-only">{text.legend}</legend>
        {OPCIONES.map((valor) => {
          const opcion = text.options[valor]
          const id = `sensibilidad-${valor}`
          return (
            <label key={valor} htmlFor={id} className="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border border-border px-3.5 py-2.5 has-checked:border-accent has-checked:bg-soft">
              <input id={id} type="radio" value={String(valor)} className="mt-1 size-4 accent-accent" {...registro} />
              <span>
                <span className="block text-[15px] font-semibold">
                  {opcion.label}
                  {valor === 2 && <span className="font-normal text-muted">{text.recommended}</span>}
                </span>
                <span className="block text-sm text-muted">{opcion.description}</span>
              </span>
            </label>
          )
        })}
      </fieldset>
      {error && (
        <p id="parametro-sensibilidad-error" className="text-[13px] text-danger">
          {error}
        </p>
      )}

      <p className="text-sm text-muted">{text.note}</p>
      <UltimoCambioDeParametro parametro={parametro} />
    </section>
  )
}
