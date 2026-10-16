import { Badge } from '../../../../shared/ui/Badge'
import { VALOR_NIVEL, type Brecha } from '../api'
import { ordenarBrechas } from '../orden'
import { dnfText } from '../text'
import { BadgeEstado } from './BadgeEstado'
import { BadgePrioridad } from './BadgePrioridad'

const text = dnfText.admin.tabla

function MiniBarra({ brecha }: { brecha: Brecha }) {
  if (brecha.brecha_porcentaje === null) return <span className="text-muted">{text.sinNivel}</span>
  const color = brecha.prioridad === 'Critica' ? 'bg-danger' : brecha.prioridad ? 'bg-warn' : 'bg-accent'
  return (
    <div className="flex items-center gap-2">
      <span aria-hidden="true" className="block h-1.5 w-16 overflow-hidden rounded-full bg-border">
        <span className={`block h-full ${color}`} style={{ width: `${Math.min(100, brecha.brecha_porcentaje)}%` }} />
      </span>
      <span>{brecha.brecha_porcentaje.toLocaleString('es-EC', { maximumFractionDigits: 2 })} %</span>
    </div>
  )
}

interface TablaDiagnosticoProps {
  brechas: Brecha[]
  /** Mensaje cuando los filtros dejan la tabla sin filas. */
  vacio?: string
}

/** Tabla del diagnóstico de un colaborador, de mayor a menor prioridad (design/DESIGN.md §4). */
export function TablaDiagnostico({ brechas, vacio = text.vacio }: TablaDiagnosticoProps) {
  const filas = ordenarBrechas(brechas)

  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface">
      <table aria-label={text.label} className="w-full min-w-175 border-collapse text-left">
        <thead>
          <tr className="text-[13px] font-semibold text-muted">
            <th scope="col" className="px-4 py-3.5">{text.columnas.competencia}</th>
            <th scope="col" className="px-4 py-3.5">{text.columnas.requerido}</th>
            <th scope="col" className="px-4 py-3.5">{text.columnas.actual}</th>
            <th scope="col" className="px-4 py-3.5">{text.columnas.brechaGrados}</th>
            <th scope="col" className="px-4 py-3.5">{text.columnas.brechaPorcentaje}</th>
            <th scope="col" className="px-4 py-3.5">{text.columnas.prioridad}</th>
            <th scope="col" className="px-4 py-3.5">{text.columnas.estado}</th>
          </tr>
        </thead>
        <tbody>
          {filas.length === 0 ? (
            <tr className="border-t border-border">
              <td colSpan={7} className="px-4 py-8 text-center text-sm text-muted">
                {vacio}
              </td>
            </tr>
          ) : (
            filas.map((brecha) => (
              <tr key={brecha.competencia_id} className="border-t border-border text-[15px] hover:bg-soft">
                <td className="px-4 py-3.5">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="font-semibold">{brecha.nombre}</span>
                    {!brecha.competencia_activa && <Badge variant="neutral">{text.inactiva}</Badge>}
                  </div>
                  <span className="text-[13px] text-muted">
                    {brecha.codigo} · {dnfText.tipoCompetencia[brecha.tipo]}
                  </span>
                </td>
                <td className="px-4 py-3.5 font-semibold" data-valor={VALOR_NIVEL[brecha.nivel_requerido]}>
                  {brecha.nivel_requerido}
                </td>
                <td className="px-4 py-3.5 font-semibold">{brecha.nivel_actual ?? <span className="font-normal text-muted">{text.sinNivel}</span>}</td>
                <td className="px-4 py-3.5">{brecha.diferencia ?? <span className="text-muted">{text.sinNivel}</span>}</td>
                <td className="px-4 py-3.5">
                  <MiniBarra brecha={brecha} />
                </td>
                <td className="px-4 py-3.5">
                  <BadgePrioridad prioridad={brecha.prioridad} />
                </td>
                <td className="px-4 py-3.5">
                  <BadgeEstado estado={brecha.estado} />
                </td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  )
}
