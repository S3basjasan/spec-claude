import { Link } from 'react-router-dom'
import { VALOR_NIVEL, type Brecha, type Nivel } from '../api'
import { dnfText } from '../text'
import { BadgePrioridad } from './BadgePrioridad'

const text = dnfText.colaborador.tarjeta

/** Barra horizontal de un nivel A–D: el ancho es proporcional a su valor (A = 100 %). */
function Barra({ etiqueta, nivel, color }: { etiqueta: string; nivel: Nivel | null; color: string }) {
  const ancho = nivel === null ? 0 : (VALOR_NIVEL[nivel] / 4) * 100
  return (
    <div className="flex items-center gap-3 text-[13px]">
      <span className="w-20 shrink-0 text-muted">{etiqueta}</span>
      <span aria-hidden="true" className="relative h-2.5 flex-1 overflow-hidden rounded-full bg-border">
        <span className={`absolute inset-y-0 left-0 rounded-full ${color}`} style={{ width: `${ancho}%` }} />
      </span>
      <span className="w-6 text-right font-semibold">{nivel ?? '—'}</span>
    </div>
  )
}

interface TarjetaBrechaProps {
  brecha: Brecha
  /** Posición 1–3 en «Mis brechas prioritarias»; sin ella la tarjeta no se numera. */
  posicion?: number
  tieneOferta: boolean
}

/** Tarjeta de una brecha del Colaborador: barras requerido contra actual, diferencia y acceso a los cursos sugeridos. */
export function TarjetaBrecha({ brecha, posicion, tieneOferta }: TarjetaBrechaProps) {
  const prioridad = brecha.prioridad ? dnfText.prioridad[brecha.prioridad] : dnfText.sinPrioridad

  return (
    <article
      aria-label={text.aria(brecha.nombre, prioridad, brecha.nivel_actual, brecha.nivel_requerido, brecha.diferencia)}
      className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5"
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-start gap-3">
          {posicion !== undefined && (
            <span aria-hidden="true" className="flex size-7 shrink-0 items-center justify-center rounded-full bg-soft text-sm font-bold text-accent">
              {posicion}
            </span>
          )}
          <h3 className="text-base font-bold">{brecha.nombre}</h3>
        </div>
        <BadgePrioridad prioridad={brecha.prioridad} />
      </div>

      <div className="flex flex-col gap-2">
        <Barra etiqueta={text.requerido} nivel={brecha.nivel_requerido} color="bg-text" />
        <Barra etiqueta={text.actual} nivel={brecha.nivel_actual} color="bg-accent" />
      </div>

      <p className="text-sm">{text.detalle(brecha.nivel_actual, brecha.nivel_requerido, brecha.diferencia, brecha.brecha_porcentaje)}</p>

      {tieneOferta ? (
        <Link
          to={`/formacion?competencia=${brecha.competencia_id}`}
          aria-label={text.verCursosDe(brecha.nombre)}
          className="inline-flex min-h-11 items-center justify-center self-start rounded-lg border border-border bg-surface px-5 text-[15px] font-semibold hover:bg-soft"
        >
          {text.verCursos}
        </Link>
      ) : (
        <div className="flex flex-col gap-1.5">
          <button
            type="button"
            disabled
            aria-describedby={`sin-cursos-${brecha.competencia_id}`}
            className="inline-flex min-h-11 cursor-not-allowed items-center justify-center self-start rounded-lg border border-border bg-surface px-5 text-[15px] font-semibold opacity-60"
          >
            {text.verCursos}
          </button>
          <p id={`sin-cursos-${brecha.competencia_id}`} className="text-[13px] text-muted">
            {text.sinCursos}
          </p>
        </div>
      )}
    </article>
  )
}
