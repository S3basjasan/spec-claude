import type { TipoMicro } from '../api'
import { ESTILO_TIPO } from '../estilosTipo'
import { microText } from '../text'

/** Píldora con el nombre del tipo y su color semántico (nunca solo color). */
export function EtiquetaTipo({ tipo }: { tipo: TipoMicro }) {
  return <span className={`inline-block rounded-full px-2.5 py-[3px] text-[13px] font-bold ${ESTILO_TIPO[tipo]}`}>{microText.tipo[tipo]}</span>
}
