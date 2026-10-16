import { Button } from '../../../../shared/ui/Button'
import type { FilaAccion } from '../api'
import { ESTILO_TIPO } from '../estilosTipo'
import { desdeIso, hoyIso, semanasDelMes } from '../fecha'
import { planText } from '../text'

const t = planText.acciones.calendario
const MAX_POR_DIA = 3

interface CalendarioAccionesProps {
  anio: number
  /** Mes de 0 a 11. */
  mes: number
  acciones: FilaAccion[]
  /** `true` si el servidor tiene más acciones de las que se pudieron traer. */
  hayMas: boolean
  onMesChange: (anio: number, mes: number) => void
  onSeleccionar: (accion: FilaAccion) => void
}

function enDia(accion: FilaAccion, dia: string): boolean {
  return accion.fecha_inicio !== null && accion.fecha_fin !== null && accion.fecha_inicio <= dia && dia <= accion.fecha_fin
}

function Chip({ accion, onSeleccionar }: { accion: FilaAccion; onSeleccionar: (accion: FilaAccion) => void }) {
  const tipo = accion.tipo === null ? 'bg-neutralsoft text-neutral' : ESTILO_TIPO[accion.tipo]
  const nombreTipo = accion.tipo === null ? planText.sinDato : planText.tipo[accion.tipo]
  return (
    <button
      type="button"
      title={accion.nombre}
      aria-label={t.accionDia(accion.nombre, nombreTipo)}
      onClick={() => onSeleccionar(accion)}
      className={`block min-h-6 w-full truncate rounded px-1.5 py-[3px] text-left text-[11px] font-semibold leading-snug ${tipo} ${accion.cancelada ? 'line-through opacity-70' : ''}`}
    >
      {accion.nombre}
    </button>
  )
}

/**
 * Calendario mensual: cada acción aparece en los días que abarca, con el color de su tipo. Hasta 3 por día y «+N más».
 * Bajo 640 px se muestra una lista por día en lugar de la rejilla.
 */
export function CalendarioAcciones({ anio, mes, acciones, hayMas, onMesChange, onSeleccionar }: CalendarioAccionesProps) {
  const semanas = semanasDelMes(anio, mes)
  const hoy = hoyIso()
  const titulo = planText.mes(anio, mes)
  const irA = (delta: number) => {
    const destino = new Date(Date.UTC(anio, mes + delta, 1))
    onMesChange(destino.getUTCFullYear(), destino.getUTCMonth())
  }
  const diasDelMes = semanas.flat().filter((d) => d.delMes)
  const diasConAcciones = diasDelMes.filter((d) => acciones.some((a) => enDia(a, d.iso)))
  const sinFecha = acciones.filter((a) => a.fecha_inicio === null || a.fecha_fin === null).length

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-lg font-bold" aria-live="polite">
          {titulo}
        </h2>
        <div className="flex gap-2">
          <Button variant="secondary" onClick={() => irA(-1)}>
            {t.anterior}
          </Button>
          <Button variant="secondary" onClick={() => onMesChange(Number(hoy.slice(0, 4)), Number(hoy.slice(5, 7)) - 1)}>
            {t.hoy}
          </Button>
          <Button variant="secondary" onClick={() => irA(1)}>
            {t.siguiente}
          </Button>
        </div>
      </div>

      {hayMas && <p role="status" className="rounded-lg bg-warnsoft px-3 py-2 text-[13px] text-warn">{t.masDe}</p>}

      <div className="hidden overflow-x-auto sm:block">
        <table aria-label={t.label(titulo)} className="w-full table-fixed border-collapse">
          <thead>
            <tr>
              {planText.diasSemana.map((dia) => (
                <th key={dia} scope="col" className="border border-border bg-bg px-2 py-2 text-[13px] font-semibold text-muted">
                  {dia}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {semanas.map((semana) => (
              <tr key={semana[0].iso}>
                {semana.map((dia) => {
                  const delDia = acciones.filter((a) => enDia(a, dia.iso))
                  return (
                    <td
                      key={dia.iso}
                      aria-current={dia.iso === hoy ? 'date' : undefined}
                      className={`h-28 border align-top p-1 ${dia.iso === hoy ? 'border-accent border-2' : 'border-border'} ${dia.delMes ? 'bg-surface' : 'bg-bg opacity-60'}`}
                    >
                      <div className="mb-1 text-[13px] font-semibold text-muted">
                        <span className="sr-only">{dia.iso}</span>
                        <span aria-hidden="true">{desdeIso(dia.iso).getUTCDate()}</span>
                      </div>
                      <div className="flex flex-col gap-0.5">
                        {delDia.slice(0, MAX_POR_DIA).map((a) => (
                          <Chip key={a.id} accion={a} onSeleccionar={onSeleccionar} />
                        ))}
                        {delDia.length > MAX_POR_DIA && <span className="px-1 text-[11px] text-muted">{planText.acciones.mas(delDia.length - MAX_POR_DIA)}</span>}
                      </div>
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <ol aria-label={t.label(titulo)} className="flex flex-col gap-3 sm:hidden">
        {diasConAcciones.length === 0 && <li className="text-[15px] text-muted">{t.diaSinAcciones}</li>}
        {diasConAcciones.map((dia) => (
          <li key={dia.iso} className="rounded-lg border border-border bg-surface p-3">
            <p className="mb-2 text-[13px] font-semibold text-muted">{planText.rango(dia.iso, dia.iso)}</p>
            <div className="flex flex-col gap-1">
              {acciones.filter((a) => enDia(a, dia.iso)).map((a) => (
                <Chip key={a.id} accion={a} onSeleccionar={onSeleccionar} />
              ))}
            </div>
          </li>
        ))}
      </ol>

      {sinFecha > 0 && <p className="text-[13px] text-muted">{t.sinFecha}</p>}
    </div>
  )
}
