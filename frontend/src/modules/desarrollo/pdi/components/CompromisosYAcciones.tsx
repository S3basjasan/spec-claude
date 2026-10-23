import type { Accion, Compromiso, EstadoAccion } from '../api'
import { rangoDeFechas } from '../fecha'
import { pdiText } from '../text'
import { FilaDeAccion } from './FilaDeAccion'

const t = pdiText.detalle

interface CompromisosYAccionesProps {
  compromisos: Compromiso[]
  puedeRegistrarAvance: boolean
  puedeVincular: boolean
  busy?: boolean
  onAvance?: (accion: Accion, estado: EstadoAccion) => void
  onVincular?: (accion: Accion) => void
  onDesvincular?: (accion: Accion) => void
}

function titulo(c: Compromiso): string {
  return c.elemento?.nombre ?? c.descripcion ?? pdiText.dimensiones[c.dimension]
}

/** Compromisos con sus acciones (solo lectura para el colaborador y para un plan Cerrado). */
export function CompromisosYAcciones({ compromisos, puedeRegistrarAvance, puedeVincular, busy = false, onAvance, onVincular, onDesvincular }: CompromisosYAccionesProps) {
  if (compromisos.length === 0) return <p className="m-0 text-muted">{t.sinCompromisos}</p>

  return (
    <div className="flex flex-col gap-4">
      {compromisos.map((c, i) => (
        <section key={c.id} aria-label={`${i + 1}. ${titulo(c)}`} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
          <header className="flex flex-wrap items-center justify-between gap-2">
            <h3 className="m-0 text-base font-bold">
              {i + 1}. {titulo(c)}
            </h3>
            <span className="text-[13px] text-muted">{pdiText.dimensiones[c.dimension]}</span>
          </header>
          <p className="m-0 text-[13px] text-muted">
            {rangoDeFechas(c.fecha_inicio, c.fecha_fin)}
            {c.nivel_actual !== null && c.nivel_objetivo !== null && ` · ${t.nivelActual}: ${c.nivel_actual} → ${t.nivelObjetivo}: ${c.nivel_objetivo}`}
            {c.indicador !== null && c.indicador !== '' && ` · ${t.indicador}: ${c.indicador}`}
            {c.resultado !== null && ` · ${t.resultado}: ${pdiText.resultadosCompromiso[c.resultado]}`}
          </p>
          <ul className="m-0 flex list-none flex-col gap-2 p-0">
            {c.acciones.map((a) => (
              <FilaDeAccion
                key={a.id}
                accion={a}
                busy={busy}
                puedeRegistrarAvance={puedeRegistrarAvance}
                puedeVincular={puedeVincular}
                onAvance={(estado) => onAvance?.(a, estado)}
                onVincular={() => onVincular?.(a)}
                onDesvincular={() => onDesvincular?.(a)}
              />
            ))}
          </ul>
        </section>
      ))}
    </div>
  )
}
