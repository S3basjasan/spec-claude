import type { BloqueTrayectoria } from '../api'
import { fichasText } from '../text'
import { NoDisponible } from './SeccionLaboral'

const text = fichasText.detail

interface TrayectoriaProps {
  trayectoria: BloqueTrayectoria
  onReintentar: () => void
}

/** Línea de tiempo de hechos laborales, del más reciente al más antiguo; los futuros se marcan «Programado». */
export function Trayectoria({ trayectoria, onReintentar }: TrayectoriaProps) {
  if (trayectoria.disponibilidad === 'no_disponible') {
    return <NoDisponible onReintentar={onReintentar} />
  }

  if (trayectoria.hechos.length === 0) {
    return <p className="text-sm text-muted">{text.trajectoryEmpty}</p>
  }

  return (
    <ol className="flex flex-col gap-3">
      {trayectoria.hechos.map((hecho) => (
        <li
          key={`${hecho.fecha_efectiva}-${hecho.tipo}`}
          className="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-l-2 border-border pl-4"
        >
          <time dateTime={hecho.fecha_efectiva} className="text-sm font-semibold">
            {hecho.fecha_efectiva}
          </time>
          <span className="text-[15px]">{hecho.tipo}</span>
          {hecho.programado && (
            <span className="rounded-full bg-border px-2.5 py-[3px] text-[13px] font-semibold text-muted">
              {text.trajectoryScheduled}
            </span>
          )}
          {hecho.detalle && <span className="basis-full text-sm text-muted">{hecho.detalle}</span>}
        </li>
      ))}
    </ol>
  )
}
