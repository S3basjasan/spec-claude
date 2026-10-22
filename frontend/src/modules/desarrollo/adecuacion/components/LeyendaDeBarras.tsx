import { adecuacionText } from '../text'

const t = adecuacionText.leyenda

function Muestra({ color }: { color: string }) {
  return <span aria-hidden="true" className={`h-2 w-[22px] rounded ${color}`} />
}

interface LeyendaProps {
  items?: ('actual' | 'persona' | 'futuro')[]
  /** Rótulos propios (la vista del colaborador dice «Tu nivel actual» y «Requisito del puesto»). */
  etiquetas?: Partial<Record<'actual' | 'persona' | 'futuro', string>>
}

export function LeyendaDeBarras({ items = ['actual', 'persona', 'futuro'], etiquetas = {} }: LeyendaProps) {
  return (
    <div aria-label={t.etiqueta} className="flex flex-wrap gap-x-5 gap-y-2 text-[13px]">
      {items.includes('actual') && (
        <span className="inline-flex items-center gap-1.5">
          <Muestra color="bg-muted opacity-60" />
          {etiquetas.actual ?? t.actual}
        </span>
      )}
      {items.includes('persona') && (
        <span className="inline-flex items-center gap-1.5">
          <Muestra color="bg-info" />
          {etiquetas.persona ?? t.persona}
        </span>
      )}
      {items.includes('futuro') && (
        <span className="inline-flex items-center gap-1.5">
          <Muestra color="bg-warning" />
          {etiquetas.futuro ?? t.futuro}
        </span>
      )}
    </div>
  )
}
