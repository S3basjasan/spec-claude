import { IconAlertTriangle } from '../../../../shared/ui/icons'
import { categoriasReconocimientoText } from '../text'

/** «Competencia inactiva»: la categoría deja de ser seleccionable mientras su competencia siga inactiva. */
export function AdvertenciaCompetencia() {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full bg-warningsoft px-2.5 py-0.5 text-[13px] font-semibold text-warning">
      <IconAlertTriangle size={14} aria-hidden="true" />
      {categoriasReconocimientoText.competenciaInactiva}
    </span>
  )
}
