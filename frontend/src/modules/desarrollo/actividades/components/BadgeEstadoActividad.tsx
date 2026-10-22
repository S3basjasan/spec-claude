import type { EstadoActividad } from '../api'
import { estilosEstado } from '../estilosEstado'
import { actividadesText } from '../text'

/** Estado calculado de una actividad: siempre con texto (design/DESIGN.md §4), nunca solo color. */
export function BadgeEstadoActividad({ estado }: { estado: EstadoActividad }) {
  return (
    <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[13px] font-bold ${estilosEstado[estado]}`}>
      {actividadesText.estado[estado]}
    </span>
  )
}
