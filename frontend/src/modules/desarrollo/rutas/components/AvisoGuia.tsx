import { IconInfoCircle } from '../../../../shared/ui/icons'
import { rutasText } from '../text'

/** Aviso permanente «guía, no promesa»: aparece en toda pantalla que muestra una ruta (RutasAdmin.dc.html). */
export function AvisoGuia() {
  return (
    <div role="note" className="flex items-start gap-3 rounded-[10px] border border-border bg-infosoft px-4 py-3 text-info">
      <IconInfoCircle size={20} className="mt-px shrink-0" />
      <p className="m-0 text-sm font-bold leading-relaxed">{rutasText.aviso}</p>
    </div>
  )
}
