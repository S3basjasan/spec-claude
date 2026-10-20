import type { ActividadColaborador } from '../api'
import { actividadesText } from '../text'
import { esUrlHttp } from '../validation'
import { BadgeAsistencia } from './BadgeAsistencia'
import { BadgeEstadoActividad } from './BadgeEstadoActividad'
import { EtiquetaTipo } from './EtiquetaTipo'

const t = actividadesText.colaborador.tarjeta
const cuando = actividadesText.colaborador.cuando

/** Días desde hoy hasta la fecha de inicio (puede ser negativo). Se compara por día calendario, sin horas. */
function diasHasta(inicio: string, hoy: Date): number {
  const [anio, mes, dia] = inicio.split('-').map(Number)
  const diaInicio = Date.UTC(anio, mes - 1, dia)
  const diaHoy = Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate())
  return Math.round((diaInicio - diaHoy) / 86_400_000)
}

function textoCuando(actividad: ActividadColaborador, hoy: Date): string | null {
  if (actividad.estado === 'EnCurso') return cuando.enCurso
  if (actividad.estado !== 'Programada' || !actividad.fecha_inicio) return null
  const dias = diasHasta(actividad.fecha_inicio, hoy)
  if (dias <= 0) return cuando.hoy
  return dias === 1 ? cuando.manana : cuando.enDias(dias)
}

/** Tarjeta de una actividad del colaborador: nunca muestra las observaciones del formador. */
export function TarjetaActividad({ actividad, hoy = new Date() }: { actividad: ActividadColaborador; hoy?: Date }) {
  const esEnlace = esUrlHttp(actividad.lugar_enlace)
  const proximidad = textoCuando(actividad, hoy)

  return (
    <article className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex flex-col gap-1.5">
          <h3 className="text-[17px] font-bold leading-snug">{actividad.nombre}</h3>
          <div className="flex flex-wrap items-center gap-2">
            <EtiquetaTipo tipo={actividad.tipo} />
            <BadgeEstadoActividad estado={actividad.estado} />
          </div>
        </div>
        {proximidad && <span className="text-sm font-semibold text-accent">{proximidad}</span>}
      </div>

      <dl className="grid gap-x-6 gap-y-2 text-[15px] sm:grid-cols-2 [&>*]:min-w-0">
        <div>
          <dt className="text-[13px] font-semibold text-muted">{t.fecha}</dt>
          <dd>{actividadesText.rango(actividad.fecha_inicio, actividad.fecha_fin)}</dd>
        </div>
        <div>
          <dt className="text-[13px] font-semibold text-muted">{esEnlace ? t.enlace : t.lugar}</dt>
          <dd>
            {esEnlace ? (
              <a href={actividad.lugar_enlace} target="_blank" rel="noopener noreferrer" className="break-all text-accent underline">
                {t.abrirEnlace}
              </a>
            ) : (
              actividad.lugar_enlace
            )}
          </dd>
        </div>
        <div>
          <dt className="text-[13px] font-semibold text-muted">{t.formador}</dt>
          <dd>{actividad.formador ?? actividadesText.sinDato}</dd>
        </div>
        <div>
          <dt className="text-[13px] font-semibold text-muted">{t.asistencia}</dt>
          <dd>
            <BadgeAsistencia asistencia={actividad.asistencia} />
          </dd>
        </div>
        <div>
          <dt className="text-[13px] font-semibold text-muted">{t.calificacion}</dt>
          <dd className="font-semibold">{actividad.calificacion === null ? <span className="font-normal text-muted">{t.sinCalificacion}</span> : `${actividad.calificacion} / 5`}</dd>
        </div>
      </dl>
    </article>
  )
}
