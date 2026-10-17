import type { TipoAccion } from '../api'
import { estilosTipo } from '../estilosEstado'
import { actividadesText } from '../text'

/** Píldora con el nombre del tipo y su color semántico (nunca solo color). */
export function EtiquetaTipo({ tipo }: { tipo: TipoAccion | null }) {
  if (tipo === null) return <span className="text-muted">{actividadesText.sinDato}</span>

  return <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[13px] font-bold ${estilosTipo[tipo]}`}>{actividadesText.tipo[tipo]}</span>
}
