import { ASISTENCIAS_REGISTRABLES, CALIFICACION_MAX, CALIFICACION_MIN, MAX_OBSERVACIONES, type AsistenciaRegistrada } from '../api'
import { actividadesText } from '../text'
import { errorDeCalificacion, type FilaBorrador } from '../registros'

const t = actividadesText.registro
const c = t.columnas

interface TablaAsistenciaProps {
  filas: FilaBorrador[]
  /** Vista deshabilitada (actividad Programada o Cancelada, o plan Cerrado). */
  deshabilitada: boolean
  onElegir: (colaboradorId: number, opcion: AsistenciaRegistrada) => void
  onCalificacion: (colaboradorId: number, valor: number | null) => void
  onObservaciones: (colaboradorId: number, texto: string) => void
}

const estiloOpcion = (seleccionada: boolean, opcion: AsistenciaRegistrada) => {
  if (!seleccionada) return 'border-border bg-surface text-text hover:bg-soft'
  if (opcion === 'Presente') return 'border-accent bg-soft text-accent'
  if (opcion === 'Ausente') return 'border-danger bg-dangersoft text-danger'
  return 'border-warning bg-warningsoft text-warning'
}

/**
 * Lista de inscritos: control Presente / Ausente / Justificado (pulsar de nuevo la opción vuelve a Pendiente),
 * calificación de 1 a 5 solo con Presente y observaciones internas solo con asistencia registrada.
 */
export function TablaAsistencia({ filas, deshabilitada, onElegir, onCalificacion, onObservaciones }: TablaAsistenciaProps) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface">
      <table aria-label={t.tableLabel} className="w-full min-w-200 border-collapse text-left">
        <thead>
          <tr className="text-[13px] font-semibold text-muted">
            <th scope="col" className="px-4 py-3.5">{c.numero}</th>
            <th scope="col" className="px-4 py-3.5">{c.participante}</th>
            <th scope="col" className="px-4 py-3.5">{c.asistencia}</th>
            <th scope="col" className="px-4 py-3.5">{c.calificacion}</th>
            <th scope="col" className="px-4 py-3.5">{c.observaciones}</th>
          </tr>
        </thead>
        <tbody>
          {filas.map((fila, indice) => {
            const errorCalificacion = errorDeCalificacion(fila, { calificacion: t.errorCalificacion })
            const idCalificacion = `calificacion-${fila.colaborador_id}`
            return (
              <tr key={fila.colaborador_id} className="border-t border-border align-top text-[15px]">
                <td className="px-4 py-3.5 text-muted">{indice + 1}</td>
                <td className="px-4 py-3.5 font-semibold">{fila.nombre}</td>
                <td className="px-4 py-3.5">
                  <div role="group" aria-label={t.control(fila.nombre)} className="flex flex-wrap gap-1.5">
                    {ASISTENCIAS_REGISTRABLES.map((opcion) => {
                      const seleccionada = fila.asistencia === opcion
                      return (
                        <button
                          key={opcion}
                          type="button"
                          aria-pressed={seleccionada}
                          disabled={deshabilitada}
                          onClick={() => onElegir(fila.colaborador_id, opcion)}
                          className={`h-10 rounded-lg border px-3 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60 ${estiloOpcion(seleccionada, opcion)}`}
                        >
                          {actividadesText.asistencia[opcion]}
                        </button>
                      )
                    })}
                  </div>
                  {fila.asistencia === 'Pendiente' && <span className="mt-1.5 inline-block text-[13px] text-muted">{actividadesText.asistencia.Pendiente}</span>}
                </td>
                <td className="px-4 py-3.5">
                  <input
                    id={idCalificacion}
                    type="number"
                    inputMode="numeric"
                    min={CALIFICACION_MIN}
                    max={CALIFICACION_MAX}
                    step={1}
                    aria-label={t.calificacionDe(fila.nombre)}
                    aria-invalid={errorCalificacion ? true : undefined}
                    aria-describedby={errorCalificacion ? `${idCalificacion}-error` : undefined}
                    disabled={deshabilitada || fila.asistencia !== 'Presente'}
                    value={fila.calificacion ?? ''}
                    onChange={(e) => onCalificacion(fila.colaborador_id, e.target.value === '' ? null : Number(e.target.value))}
                    className="h-10 w-20 rounded-lg border border-input-border bg-surface px-3 text-[15px] disabled:cursor-not-allowed disabled:opacity-60"
                  />
                  {errorCalificacion && (
                    <p id={`${idCalificacion}-error`} role="alert" className="mt-1 text-[13px] text-danger">
                      {errorCalificacion}
                    </p>
                  )}
                </td>
                <td className="px-4 py-3.5">
                  <input
                    type="text"
                    aria-label={t.observacionesDe(fila.nombre)}
                    maxLength={MAX_OBSERVACIONES}
                    disabled={deshabilitada || fila.asistencia === 'Pendiente'}
                    value={fila.observaciones}
                    onChange={(e) => onObservaciones(fila.colaborador_id, e.target.value)}
                    className="h-10 w-full min-w-48 rounded-lg border border-input-border bg-surface px-3 text-[15px] disabled:cursor-not-allowed disabled:opacity-60"
                  />
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
