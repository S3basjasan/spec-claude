import { organigramaText } from '../text'

/** Muestras de los dos estilos de tarjeta y el aviso de que las posiciones suprimidas no se muestran. */
export function Leyenda() {
  const text = organigramaText.legend

  return (
    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] text-muted">
      <span className="inline-flex items-center gap-2">
        <span aria-hidden="true" className="h-3 w-4.5 rounded-[3px] border-[1.5px] border-border bg-surface" />
        {text.occupied}
      </span>
      <span className="inline-flex items-center gap-2">
        <span aria-hidden="true" className="h-3 w-4.5 rounded-[3px] border-[1.5px] border-dashed border-warning bg-warningsoft" />
        {text.vacant}
      </span>
      <span>{text.suppressed}</span>
    </div>
  )
}
