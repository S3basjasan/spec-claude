import { NIVELES, VALOR_NIVEL, type Brecha } from '../api'
import { dnfText } from '../text'

const text = dnfText.admin.grafico

interface GraficoPerfilProps {
  nombreColaborador: string
  brechas: Brecha[]
}

const pct = (nivel: keyof typeof VALOR_NIVEL) => (VALOR_NIVEL[nivel] / 4) * 100

/**
 * Perfil ideal (marca del nivel requerido) contra perfil actual (barra), una fila por competencia, con el tramo de brecha
 * entre ambos. Es un complemento visual: la tabla del diagnóstico es su equivalente accesible, por eso el gráfico
 * se expone como una sola imagen con un resumen.
 */
export function GraficoPerfil({ nombreColaborador, brechas }: GraficoPerfilProps) {
  return (
    <section className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
      <div>
        <h3 className="text-lg font-bold">{text.titulo}</h3>
        <p className="text-sm text-muted">{text.descripcion(nombreColaborador)}</p>
      </div>

      <div role="img" aria-label={text.resumen(nombreColaborador, brechas.length)} className="flex flex-col gap-2.5">
        <div className="flex items-center gap-3 text-[13px] text-muted" aria-hidden="true">
          <span className="w-40 shrink-0 sm:w-56" />
          <span className="relative flex flex-1 justify-between">
            {[...NIVELES].reverse().map((nivel) => (
              <span key={nivel}>{nivel}</span>
            ))}
          </span>
        </div>
        {brechas.map((b) => {
          const actual = b.nivel_actual === null ? 0 : pct(b.nivel_actual)
          const requerido = pct(b.nivel_requerido)
          return (
            <div key={b.competencia_id} data-fila={b.competencia_id} className="flex items-center gap-3 text-sm">
              <span className="w-40 shrink-0 truncate sm:w-56" title={text.fila(b.nombre, b.nivel_requerido, b.nivel_actual)}>
                {b.nombre}
              </span>
              <span className="relative h-4 flex-1 rounded bg-border">
                <span className="absolute inset-y-0 left-0 rounded bg-accent" style={{ width: `${actual}%` }} />
                {b.estado === 'ConBrecha' && (
                  <span
                    data-brecha="true"
                    className="absolute inset-y-0 border-2 border-dashed border-warn bg-warnsoft"
                    style={{ left: `${actual}%`, width: `${requerido - actual}%` }}
                  />
                )}
                <span data-requerido="true" className="absolute -inset-y-1 w-0.5 bg-text" style={{ left: `calc(${requerido}% - 1px)` }} />
              </span>
              {b.nivel_actual === null && <span className="text-xs text-muted">{text.sinNivel}</span>}
            </div>
          )
        })}
      </div>

      <ul aria-hidden="true" className="flex flex-wrap gap-x-5 gap-y-1 text-[13px] text-muted">
        <li className="flex items-center gap-2">
          <span className="inline-block h-3 w-5 rounded bg-accent" />
          {text.actual}
        </li>
        <li className="flex items-center gap-2">
          <span className="inline-block h-4 w-0.5 bg-text" />
          {text.ideal}
        </li>
        <li className="flex items-center gap-2">
          <span className="inline-block h-3 w-5 border-2 border-dashed border-warn bg-warnsoft" />
          {text.brecha}
        </li>
      </ul>
    </section>
  )
}
