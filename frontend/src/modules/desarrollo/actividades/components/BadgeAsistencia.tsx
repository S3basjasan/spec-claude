import type { Asistencia } from '../api'
import { estiloAsistenciaPendiente, estilosAsistencia } from '../estilosEstado'
import { actividadesText } from '../text'

/** Asistencia de un participante (Pendiente, Presente, Ausente o Justificado), siempre con texto. */
export function BadgeAsistencia({ asistencia }: { asistencia: Asistencia }) {
  return (
    <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[13px] font-bold ${estilosAsistencia[asistencia]}`}>
      {actividadesText.asistencia[asistencia]}
    </span>
  )
}

/** Badge adicional de una actividad Finalizada con inscritos sin registro. */
export function BadgeAsistenciaPendiente() {
  return (
    <span className={`inline-block rounded-full px-2.5 py-[3px] text-[13px] font-bold ${estiloAsistenciaPendiente}`}>
      {actividadesText.asistenciaPendiente}
    </span>
  )
}
