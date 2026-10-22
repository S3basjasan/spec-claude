import { Button } from '../../../../shared/ui/Button'
import type { FilaImpacto } from '../api'
import { estiloEvaluacion } from '../estilosEstado'
import { dolares, fecha, porcentaje } from '../formato'
import { reportesText } from '../text'

const t = reportesText.impactos

interface TablaImpactosProps {
  filas: FilaImpacto[]
  onRegistrar: (fila: FilaImpacto) => void
}

/** Acciones Finalizadas con el indicador «Con evaluación» / «Sin evaluación», su ROI y el botón para registrar o editar. */
export function TablaImpactos({ filas, onRegistrar }: TablaImpactosProps) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface">
      <table aria-label={t.tableLabel} className="w-full min-w-205 border-collapse text-left text-sm">
        <thead>
          <tr className="border-b border-border text-xs uppercase tracking-wide text-muted">
            <th scope="col" className="px-4 py-3 font-semibold">{t.columnas.accion}</th>
            <th scope="col" className="px-3 py-3 font-semibold">{t.columnas.plan}</th>
            <th scope="col" className="px-3 py-3 font-semibold">{t.columnas.fin}</th>
            <th scope="col" className="px-3 py-3 text-right font-semibold">{t.columnas.costo}</th>
            <th scope="col" className="px-3 py-3 font-semibold">{t.columnas.estado}</th>
            <th scope="col" className="px-3 py-3 text-right font-semibold">{t.columnas.roi}</th>
            <th scope="col" className="px-4 py-3 text-right font-semibold">{t.columnas.acciones}</th>
          </tr>
        </thead>
        <tbody>
          {filas.map((f) => (
            <tr key={f.plan_accion_id} className="border-b border-border last:border-b-0">
              <td className="px-4 py-3">
                <div className="font-semibold">{f.accion_nombre}</div>
                <div className="text-xs text-muted">{reportesText.nombreDeTipo(f.tipo)}</div>
              </td>
              <td className="px-3 py-3">
                <div>{f.plan_codigo}</div>
                <div className="text-xs text-muted">{f.plan_nombre}</div>
              </td>
              <td className="whitespace-nowrap px-3 py-3">{fecha(f.fecha_fin)}</td>
              <td className="px-3 py-3 text-right tabular-nums">{dolares(f.costo, 2)}</td>
              <td className="px-3 py-3">
                <span className={`inline-block rounded-full px-2.5 py-0.75 text-[13px] font-semibold ${f.evaluada ? estiloEvaluacion.con : estiloEvaluacion.sin}`}>
                  {f.evaluada ? t.conEvaluacion : t.sinEvaluacion}
                </span>
              </td>
              <td className="px-3 py-3 text-right tabular-nums">{f.evaluada ? (f.roi === null ? t.roiNoAplica : porcentaje(f.roi)) : reportesText.sinDato}</td>
              <td className="px-4 py-3 text-right">
                <Button variant="secondary" aria-label={f.evaluada ? t.editarDe(f.accion_nombre) : t.registrarDe(f.accion_nombre)} onClick={() => onRegistrar(f)}>
                  {f.evaluada ? t.editar : t.registrar}
                </Button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
