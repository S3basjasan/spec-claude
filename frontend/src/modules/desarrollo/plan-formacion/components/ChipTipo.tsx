import type { TipoAccion } from '../api'
import { ESTILO_TIPO } from '../estilosTipo'
import { planText } from '../text'

/** Tipo de una acción: siempre con el nombre como texto, nunca solo con color. */
export function ChipTipo({ tipo }: { tipo: TipoAccion | null }) {
  if (tipo === null) return <span className="text-muted">{planText.sinDato}</span>
  return (
    <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[13px] font-bold ${ESTILO_TIPO[tipo]}`}>{planText.tipo[tipo]}</span>
  )
}
