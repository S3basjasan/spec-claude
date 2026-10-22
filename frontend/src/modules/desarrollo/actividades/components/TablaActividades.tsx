import { Link } from 'react-router-dom'
import type { ActividadResumen } from '../api'
import { esUrlHttp } from '../validation'
import { actividadesText } from '../text'
import { BadgeAsistenciaPendiente } from './BadgeAsistencia'
import { BadgeEstadoActividad } from './BadgeEstadoActividad'
import { EtiquetaTipo } from './EtiquetaTipo'

const t = actividadesText.admin
const c = t.columnas

interface TablaActividadesProps {
  actividades: ActividadResumen[]
  onEditar: (id: number) => void
}

/** Lugar o enlace: las URL se abren en otra pestaña; el texto libre se muestra tal cual. */
function LugarOEnlace({ valor }: { valor: string }) {
  if (!esUrlHttp(valor)) return <span>{valor}</span>

  return (
    <a href={valor} target="_blank" rel="noopener noreferrer" className="break-all text-accent underline">
      {valor.replace(/^https?:\/\//, '')}
    </a>
  )
}

/** Tabla de actividades (patrón Listado): sin botón de eliminar ni de cancelar; la cancelación se hace en el plan. */
export function TablaActividades({ actividades, onEditar }: TablaActividadesProps) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface">
      <table aria-label={t.tableLabel} className="w-full min-w-240 border-collapse text-left">
        <thead>
          <tr className="text-[13px] font-semibold text-muted">
            <th scope="col" className="px-2 py-3">{c.codigo}</th>
            <th scope="col" className="px-2 py-3">{c.nombre}</th>
            <th scope="col" className="px-2 py-3">{c.tipo}</th>
            <th scope="col" className="px-2 py-3">{c.fechas}</th>
            <th scope="col" className="px-2 py-3">{c.lugar}</th>
            <th scope="col" className="px-2 py-3">{c.formador}</th>
            <th scope="col" className="px-2 py-3">{c.departamento}</th>
            <th scope="col" className="px-2 py-3">{c.capacidad}</th>
            <th scope="col" className="px-2 py-3">{c.inscritos}</th>
            <th scope="col" className="px-2 py-3">{c.estado}</th>
            <th scope="col" className="px-2 py-3 text-right">{c.acciones}</th>
          </tr>
        </thead>
        <tbody>
          {actividades.map((actividad) => (
            <tr key={actividad.id} className="border-t border-border align-top text-[13.5px] hover:bg-soft">
              <td className="whitespace-nowrap px-2 py-3 font-semibold">{actividad.codigo}</td>
              <td className="px-2 py-3 font-semibold">{actividad.nombre}</td>
              <td className="px-2 py-3">
                <EtiquetaTipo tipo={actividad.tipo} />
              </td>
              <td className="min-w-28 px-2 py-3">{actividadesText.rango(actividad.fecha_inicio, actividad.fecha_fin)}</td>
              <td className="max-w-44 px-2 py-3">
                <LugarOEnlace valor={actividad.lugar_enlace} />
              </td>
              <td className="px-2 py-3">{actividad.formador ?? actividadesText.sinDato}</td>
              <td className="px-2 py-3">{actividad.departamento?.nombre ?? actividadesText.sinDato}</td>
              <td className="px-2 py-3">{actividad.capacidad ?? actividadesText.sinDato}</td>
              <td className="px-2 py-3">{actividad.inscritos}</td>
              <td className="px-2 py-3">
                <div className="flex flex-col items-start gap-1.5">
                  <BadgeEstadoActividad estado={actividad.estado} />
                  {actividad.asistencia_pendiente && <BadgeAsistenciaPendiente />}
                </div>
              </td>
              <td className="px-2 py-3">
                <div className="flex flex-col items-end gap-2">
                  <Link
                    to={`/actividades/${actividad.id}/asistencia`}
                    aria-label={t.acciones.asistenciaDe(actividad.nombre)}
                    className="inline-flex h-10 items-center rounded-lg border border-border bg-surface px-2.5 text-sm font-semibold hover:bg-soft"
                  >
                    {t.acciones.asistencia}
                  </Link>
                  <button
                    type="button"
                    aria-label={t.acciones.editarDe(actividad.nombre)}
                    onClick={() => onEditar(actividad.id)}
                    className="inline-flex h-10 items-center rounded-lg border border-border bg-surface px-2.5 text-sm font-semibold hover:bg-soft"
                  >
                    {t.acciones.editar}
                  </button>
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
