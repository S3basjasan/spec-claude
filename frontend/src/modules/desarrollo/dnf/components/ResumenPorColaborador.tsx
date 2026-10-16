import type { ResultadoColaborador } from '../api'
import { dnfText } from '../text'
import { BadgePrioridad } from './BadgePrioridad'

const text = dnfText.admin.resumen

interface ResumenPorColaboradorProps {
  colaboradores: ResultadoColaborador[]
  onElegir: (colaboradorId: number) => void
}

/** Resumen de una DNF masiva: brechas y prioridad más alta de cada colaborador; cada fila abre su diagnóstico. */
export function ResumenPorColaborador({ colaboradores, onElegir }: ResumenPorColaboradorProps) {
  return (
    <section aria-labelledby="dnf-resumen" className="flex flex-col gap-3">
      <h3 id="dnf-resumen" className="text-lg font-bold">
        {text.titulo}
      </h3>
      <div className="overflow-x-auto rounded-xl border border-border bg-surface">
        <table aria-labelledby="dnf-resumen" className="w-full min-w-150 border-collapse text-left">
          <thead>
            <tr className="text-[13px] font-semibold text-muted">
              <th scope="col" className="px-4 py-3.5">{text.columnas.colaborador}</th>
              <th scope="col" className="px-4 py-3.5">{text.columnas.puesto}</th>
              <th scope="col" className="px-4 py-3.5">{text.columnas.brechas}</th>
              <th scope="col" className="px-4 py-3.5">{text.columnas.prioridad}</th>
              <th scope="col" className="px-4 py-3.5 text-right">{text.columnas.accion}</th>
            </tr>
          </thead>
          <tbody>
            {colaboradores.map((c) => (
              <tr key={c.colaborador_id} className="border-t border-border text-[15px] hover:bg-soft">
                <td className="px-4 py-3.5 font-semibold">{c.nombre}</td>
                <td className="px-4 py-3.5">{c.puesto?.nombre ?? dnfText.noDisponible}</td>
                <td className="px-4 py-3.5">{c.cantidad_brechas}</td>
                <td className="px-4 py-3.5">
                  <BadgePrioridad prioridad={c.prioridad_mas_alta} />
                </td>
                <td className="px-4 py-3.5 text-right">
                  <button
                    type="button"
                    aria-label={text.ver(c.nombre)}
                    onClick={() => onElegir(c.colaborador_id)}
                    className="min-h-11 rounded-lg px-3 font-semibold text-accent hover:bg-soft"
                  >
                    {text.verBoton}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}
