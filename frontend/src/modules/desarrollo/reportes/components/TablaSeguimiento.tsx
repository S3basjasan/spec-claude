import type { FilaSeguimiento, HitoSeguimientoAdmin } from '../api'
import { estiloAlertaReingreso } from '../estilosEstado'
import { fecha, porcentaje, promedio } from '../formato'
import { reportesText } from '../text'

const t = reportesText.seguimiento

function CeldaDeHito({ h }: { h: HitoSeguimientoAdmin }) {
  if (h.respuestas === 0) {
    return (
      <span className="text-muted" aria-label={t.celdaAria(h.hito, 0, porcentaje(h.tasa_respuesta, 0), promedio(null), promedio(null))}>
        {t.sinRespuestas}
      </span>
    )
  }
  return (
    <div aria-label={t.celdaAria(h.hito, h.respuestas, porcentaje(h.tasa_respuesta, 0), promedio(h.promedio_aplicacion), promedio(h.promedio_apoyo))} className="flex flex-col gap-0.5 text-[13px]">
      <span className="font-semibold">
        {t.celda.aplicacion} {promedio(h.promedio_aplicacion)}
      </span>
      <span>
        {t.celda.apoyo} {promedio(h.promedio_apoyo)}
      </span>
      <span className="text-muted">
        {h.respuestas} resp. · {porcentaje(h.tasa_respuesta, 0)}
      </span>
    </div>
  )
}

/** Una fila por actividad Finalizada: tasa de respuesta, promedio de aplicación y de apoyo por hito, y la alerta de reingreso. */
export function TablaSeguimiento({ filas }: { filas: FilaSeguimiento[] }) {
  const conAlerta = filas.filter((f) => f.alerta_reingreso)

  return (
    <div className="flex flex-col gap-3">
      {conAlerta.length > 0 && (
        <div role="alert" className="rounded-lg border border-danger bg-dangersoft px-4 py-3 text-sm text-danger">
          <p className="m-0">
            <strong>{t.alertaTitulo(conAlerta.length)}</strong> {t.alertaTexto}
          </p>
        </div>
      )}
      <div className="overflow-x-auto rounded-xl border border-border bg-surface">
        <table aria-label={t.tableLabel} className="w-full min-w-215 border-collapse text-left text-sm">
          <thead>
            <tr className="border-b border-border text-xs uppercase tracking-wide text-muted">
              <th scope="col" className="px-4 py-3 font-semibold">{t.columnas.actividad}</th>
              <th scope="col" className="px-3 py-3 text-right font-semibold">{t.columnas.elegibles}</th>
              {[30, 60, 90].map((h) => (
                <th key={h} scope="col" className="px-3 py-3 font-semibold">{t.columnas.hito(h as 30 | 60 | 90)}</th>
              ))}
              <th scope="col" className="px-4 py-3 font-semibold">{t.columnas.reingreso}</th>
            </tr>
          </thead>
          <tbody>
            {filas.map((f) => (
              <tr key={f.actividad_id} className="border-b border-border align-top last:border-b-0">
                <td className="px-4 py-3">
                  <div className="font-semibold">{f.nombre}</div>
                  <div className="text-xs text-muted">
                    {f.codigo} · Cierre: {fecha(f.fecha_fin)}
                  </div>
                </td>
                <td className="px-3 py-3 text-right tabular-nums">{f.elegibles}</td>
                {f.hitos.map((h) => (
                  <td key={h.hito} className="px-3 py-3">
                    <CeldaDeHito h={h} />
                  </td>
                ))}
                <td className="px-4 py-3">
                  {f.alerta_reingreso ? (
                    <span className={`inline-block rounded-full px-2.5 py-0.75 text-[13px] font-semibold ${estiloAlertaReingreso}`}>{t.alerta}</span>
                  ) : (
                    <span className="text-muted">{t.sinAlerta}</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="m-0 text-[13px] text-muted">{t.nota}</p>
    </div>
  )
}
