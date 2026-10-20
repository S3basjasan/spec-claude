import type { ActividadColaborador, ResumenColaborador } from '../api'
import { actividadesText } from '../text'
import { TarjetaActividad } from './TarjetaActividad'

const t = actividadesText.colaborador

/** Resumen informativo: no afecta el progreso del plan. «—» cuando no hay datos para calcularlo. */
export function ResumenDelColaborador({ resumen }: { resumen: ResumenColaborador }) {
  const r = t.resumen
  const porRealizar = Math.max(0, resumen.inscritas - resumen.finalizadas)
  const tarjetas: [string, string, string][] = [
    [r.inscritas, String(resumen.inscritas), r.porRealizar(porRealizar)],
    [r.finalizadas, String(resumen.finalizadas), r.enHistorial],
    [
      r.asistencia,
      resumen.asistencia_porcentaje === null ? actividadesText.sinDato : `${resumen.asistencia_porcentaje} %`,
      r.asistenciaNota(resumen.presentes, resumen.asistencias_registradas),
    ],
    [
      r.calificacion,
      resumen.calificacion_promedio === null ? actividadesText.sinDato : r.calificacionValor(actividadesText.promedio(resumen.calificacion_promedio)),
      r.calificadas(resumen.calificadas),
    ],
  ]

  return (
    <ul aria-label={t.resumenLabel} className="m-0 grid list-none grid-cols-1 gap-4 p-0 sm:grid-cols-2 lg:grid-cols-4">
      {tarjetas.map(([etiqueta, valor, nota]) => (
        <li key={etiqueta} className="rounded-xl border border-border bg-surface p-4">
          <div className="text-[13px] font-semibold text-muted">{etiqueta}</div>
          <div className="mt-1 text-2xl font-bold">{valor}</div>
          <div className="mt-0.5 text-[13px] text-muted">{nota}</div>
        </li>
      ))}
    </ul>
  )
}

interface SeccionesColaboradorProps {
  proximas: ActividadColaborador[]
  historial: ActividadColaborador[]
  hoy?: Date
}

/** «Próximas actividades» (Programadas o En curso, por fecha de inicio) e «Historial» (Finalizadas o Canceladas, de la más reciente). */
export function SeccionesColaborador({ proximas, historial, hoy }: SeccionesColaboradorProps) {
  return (
    <>
      <section aria-labelledby="actividades-proximas" className="flex flex-col gap-3">
        <h2 id="actividades-proximas" className="text-xl font-bold">
          {t.proximas}
        </h2>
        {proximas.length === 0 ? <p className="text-muted">{t.sinProximas}</p> : proximas.map((a) => <TarjetaActividad key={a.id} actividad={a} hoy={hoy} />)}
      </section>
      <section aria-labelledby="actividades-historial" className="flex flex-col gap-3">
        <h2 id="actividades-historial" className="text-xl font-bold">
          {t.historial}
        </h2>
        {historial.length === 0 ? <p className="text-muted">{t.sinHistorial}</p> : historial.map((a) => <TarjetaActividad key={a.id} actividad={a} hoy={hoy} />)}
      </section>
    </>
  )
}
