import { Button } from '../../../../shared/ui/Button'
import type { CalificacionActividad, CalificacionParticipante } from '../api'
import { actividadesText } from '../text'
import { EtiquetaTipo } from './EtiquetaTipo'

const t = actividadesText.calificaciones

const envoltura = 'overflow-x-auto rounded-xl border border-border bg-surface'

/** Calificaciones por actividad: participantes calificados y promedio (sin valor si no hay notas). */
export function TablaPorActividad({ filas }: { filas: CalificacionActividad[] }) {
  const c = t.columnasActividad

  return (
    <div className={envoltura}>
      <table aria-label={t.tablaActividad} className="w-full min-w-175 border-collapse text-left">
        <thead>
          <tr className="text-[13px] font-semibold text-muted">
            <th scope="col" className="px-4 py-3.5">{c.nombre}</th>
            <th scope="col" className="px-4 py-3.5">{c.tipo}</th>
            <th scope="col" className="px-4 py-3.5">{c.fechas}</th>
            <th scope="col" className="px-4 py-3.5 text-right">{c.calificados}</th>
            <th scope="col" className="px-4 py-3.5 text-right">{c.promedio}</th>
          </tr>
        </thead>
        <tbody>
          {filas.map((fila) => (
            <tr key={fila.id} className="border-t border-border text-[15px] hover:bg-soft">
              <td className="px-4 py-3.5">
                <span className="font-semibold">{fila.nombre}</span>
                <span className="ml-2 text-[13px] text-muted">{fila.codigo}</span>
              </td>
              <td className="px-4 py-3.5">
                <EtiquetaTipo tipo={fila.tipo} />
              </td>
              <td className="whitespace-nowrap px-4 py-3.5">{actividadesText.rango(fila.fecha_inicio, fila.fecha_fin)}</td>
              <td className="px-4 py-3.5 text-right">{fila.calificados}</td>
              <td className="px-4 py-3.5 text-right font-semibold">{actividadesText.promedio(fila.promedio)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

interface TablaPorParticipanteProps {
  filas: CalificacionParticipante[]
  seleccionado: number | null
  onElegir: (colaboradorId: number) => void
}

/** Calificaciones por participante: solo quienes tienen al menos una calificación en el filtro. */
export function TablaPorParticipante({ filas, seleccionado, onElegir }: TablaPorParticipanteProps) {
  const c = t.columnasParticipante

  return (
    <div className={envoltura}>
      <table aria-label={t.tablaParticipante} className="w-full min-w-150 border-collapse text-left">
        <thead>
          <tr className="text-[13px] font-semibold text-muted">
            <th scope="col" className="px-4 py-3.5">{c.nombre}</th>
            <th scope="col" className="px-4 py-3.5 text-right">{c.calificadas}</th>
            <th scope="col" className="px-4 py-3.5 text-right">{c.promedio}</th>
            <th scope="col" className="px-4 py-3.5 text-right">{c.detalle}</th>
          </tr>
        </thead>
        <tbody>
          {filas.map((fila) => (
            <tr key={fila.colaborador_id} className={`border-t border-border text-[15px] hover:bg-soft ${seleccionado === fila.colaborador_id ? 'bg-soft' : ''}`}>
              <td className="px-4 py-3.5 font-semibold">{fila.nombre}</td>
              <td className="px-4 py-3.5 text-right">{fila.actividades_calificadas}</td>
              <td className="px-4 py-3.5 text-right font-semibold">{actividadesText.promedio(fila.promedio)}</td>
              <td className="px-4 py-3.5 text-right">
                <Button variant="secondary" aria-label={t.verDetalle(fila.nombre)} aria-pressed={seleccionado === fila.colaborador_id} onClick={() => onElegir(fila.colaborador_id)}>
                  {c.detalle}
                </Button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
