import type { Parametro } from '../api'
import { fechaLarga, valorLegible } from '../formato'
import { parametrosText } from '../text'

const text = parametrosText.lastChange

/** «Último cambio: {nombre}, {fecha} (de {anterior} a {nuevo})»; en el primer cambio, «de valor por defecto a {nuevo}». */
export function UltimoCambioDeParametro({ parametro }: { parametro: Parametro }) {
  const cambio = parametro.ultimo_cambio
  if (cambio === null) {
    return <p className="text-[13px] text-muted">{text.initial}</p>
  }

  const anterior = cambio.valor_anterior === null ? text.fromDefault : text.from(valorLegible(parametro.clave, cambio.valor_anterior))
  const nuevo = text.to(valorLegible(parametro.clave, cambio.valor_nuevo))

  return <p className="text-[13px] text-muted">{text.by(cambio.por, fechaLarga(cambio.en), anterior, nuevo)}</p>
}
