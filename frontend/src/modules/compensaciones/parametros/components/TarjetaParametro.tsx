import type { ReactNode } from 'react'
import type { UseFormRegisterReturn } from 'react-hook-form'
import { TextField } from '../../../../shared/ui/TextField'
import type { Parametro } from '../api'
import { fechaLarga } from '../formato'
import { parametrosText } from '../text'
import { UltimoCambioDeParametro } from './UltimoCambioDeParametro'

type ClaveNumerica = keyof typeof parametrosText.parametros

interface TarjetaParametroProps {
  parametro: Parametro & { clave: ClaveNumerica }
  registro: UseFormRegisterReturn
  error?: string
  /** Contenido extra bajo el campo (p. ej. la vista previa de los puntos). */
  children?: ReactNode
}

const text = parametrosText

/** Tarjeta de un parámetro numérico: título, explicación, campo, rango, cuándo aplica y último cambio. */
export function TarjetaParametro({ parametro, registro, error, children }: TarjetaParametroProps) {
  const textos = text.parametros[parametro.clave]
  const id = `parametro-${parametro.clave}`
  return (
    <section aria-labelledby={`${id}-titulo`} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
      <div>
        <h2 id={`${id}-titulo`} className="text-lg font-semibold">
          {textos.title}
        </h2>
        <p className="mt-1 text-sm text-muted">{textos.description}</p>
      </div>

      <div className="flex flex-col gap-1">
        <TextField
          id={id}
          label={text.fields.units[textos.unit]}
          inputMode="numeric"
          autoComplete="off"
          error={error}
          hint={text.fields.range(parametro.minimo, parametro.maximo)}
          className="max-w-60"
          {...registro}
        />
        <p className="text-sm text-muted">{text.fields.current(String(parametro.valor))}</p>
        {parametro.valor_programado && (
          <p className="text-sm font-semibold">{text.fields.scheduled(parametro.valor_programado.valor, fechaLarga(parametro.valor_programado.vigente_desde))}</p>
        )}
      </div>

      {children}

      <p className="text-sm text-muted">{textos.applies}</p>
      <UltimoCambioDeParametro parametro={parametro} />
    </section>
  )
}
