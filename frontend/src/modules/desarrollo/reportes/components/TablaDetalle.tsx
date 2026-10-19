import { Button } from '../../../../shared/ui/Button'
import type { FilaDetalle } from '../api'
import { estiloEstado } from '../estilosEstado'
import { dolares, fecha, horas, numero, promedio } from '../formato'
import { reportesText } from '../text'

const t = reportesText.detalle

interface TablaDetalleProps {
  filas: FilaDetalle[]
  onVerComentarios: (fila: FilaDetalle) => void
}

function rango(inicio: string | null, fin: string | null): string {
  return inicio === fin ? fecha(fin) : `${fecha(inicio)} – ${fecha(fin)}`
}

/** Tabla detallada: una fila por actividad En curso o Finalizada del período; desde cada fila se abren sus comentarios. */
export function TablaDetalle({ filas, onVerComentarios }: TablaDetalleProps) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface">
      <table aria-label={t.tableLabel} className="w-full min-w-245 border-collapse text-left text-sm">
        <thead>
          <tr className="border-b border-border text-xs uppercase tracking-wide text-muted">
            <th scope="col" className="px-4 py-3 font-semibold">{t.columnas.nombre}</th>
            <th scope="col" className="px-3 py-3 font-semibold">{t.columnas.tipo}</th>
            <th scope="col" className="px-3 py-3 font-semibold">{t.columnas.fechas}</th>
            <th scope="col" className="px-3 py-3 font-semibold">{t.columnas.departamento}</th>
            <th scope="col" className="px-3 py-3 text-right font-semibold">{t.columnas.asistentes}</th>
            <th scope="col" className="px-3 py-3 text-right font-semibold">{t.columnas.horas}</th>
            <th scope="col" className="px-3 py-3 text-right font-semibold">{t.columnas.costo}</th>
            <th scope="col" className="px-3 py-3 text-right font-semibold">{t.columnas.calificacion}</th>
            <th scope="col" className="px-3 py-3 text-right font-semibold">{t.columnas.satisfaccion}</th>
            <th scope="col" className="px-4 py-3 text-right font-semibold">{t.columnas.acciones}</th>
          </tr>
        </thead>
        <tbody>
          {filas.map((f) => (
            <tr key={f.actividad_id} className="border-b border-border last:border-b-0">
              <td className="px-4 py-3">
                <div className="font-semibold">{f.nombre}</div>
                <div className="flex items-center gap-2 text-xs text-muted">
                  <span>{f.codigo}</span>
                  <span className={`inline-block rounded-full px-2 py-px text-[11px] font-semibold ${estiloEstado[f.estado]}`}>{reportesText.nombreDeEstado(f.estado)}</span>
                </div>
              </td>
              <td className="px-3 py-3">{reportesText.nombreDeTipo(f.tipo)}</td>
              <td className="whitespace-nowrap px-3 py-3">{rango(f.fecha_inicio, f.fecha_fin)}</td>
              <td className="px-3 py-3">{f.departamento ?? reportesText.sinDato}</td>
              <td className="px-3 py-3 text-right tabular-nums">
                {f.asistentes} / {f.inscritos}
              </td>
              <td className="px-3 py-3 text-right tabular-nums">{horas(f.horas_persona)}</td>
              <td className="px-3 py-3 text-right tabular-nums">{dolares(f.costo, 2)}</td>
              <td className="px-3 py-3 text-right tabular-nums">{promedio(f.calificacion_promedio)}</td>
              <td className="px-3 py-3 text-right tabular-nums">
                {promedio(f.satisfaccion_promedio)}
                <span className="block text-xs text-muted">{numero(f.respuestas_satisfaccion)} resp.</span>
              </td>
              <td className="px-4 py-3 text-right">
                <Button variant="secondary" aria-label={t.verComentariosDe(f.nombre)} onClick={() => onVerComentarios(f)}>
                  {t.verComentarios}
                </Button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
