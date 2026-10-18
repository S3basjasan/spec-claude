import { IconZoomIn, IconZoomOut } from '../../../../shared/ui/icons'
import { ZOOM_MAXIMO, ZOOM_MINIMO } from '../hooks/useEstadoDelArbol'
import { organigramaText } from '../text'

interface ControlesDeZoomProps {
  zoom: number
  onAlejar: () => void
  onAcercar: () => void
  onRestablecer: () => void
}

const boton =
  'inline-flex h-11 min-w-11 cursor-pointer items-center justify-center bg-transparent px-2.5 text-sm font-semibold text-text hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45'

/** Alejar, porcentaje (restablece el 100 %) y Acercar: de 50 % a 150 % en pasos de 10 %. */
export function ControlesDeZoom({ zoom, onAlejar, onAcercar, onRestablecer }: ControlesDeZoomProps) {
  const text = organigramaText.zoom

  return (
    <div className="flex items-center overflow-hidden rounded-lg border border-border bg-surface shadow-sm">
      <button type="button" className={boton} aria-label={text.zoomOut} disabled={zoom <= ZOOM_MINIMO} onClick={onAlejar}>
        <IconZoomOut size={18} />
      </button>
      <button
        type="button"
        className={`${boton} border-x border-border tabular-nums`}
        aria-label={text.reset}
        onClick={onRestablecer}
      >
        {text.percent(zoom)}
      </button>
      <button type="button" className={boton} aria-label={text.zoomIn} disabled={zoom >= ZOOM_MAXIMO} onClick={onAcercar}>
        <IconZoomIn size={18} />
      </button>
    </div>
  )
}
