import { Link } from 'react-router-dom'
import type { RutaResumen } from '../api'
import { rutasText } from '../text'
import { BadgeEstadoRuta } from './Etiquetas'

const t = rutasText.lista.tarjeta

/** Peldaños de la tarjeta: una barra por nivel, creciente; apagada si la ruta está Archivada (RutasAdmin.dc.html). */
function Peldanos({ niveles, archivada }: { niveles: number; archivada: boolean }) {
  const maximo = Math.max(1, niveles)
  return (
    <div aria-hidden="true" className="flex h-9 items-end gap-1">
      {Array.from({ length: niveles }, (_, i) => (
        <span
          key={i}
          className={`flex-1 rounded-t-[3px] ${archivada ? 'bg-border' : 'bg-accent'}`}
          style={{ opacity: 0.35 + (0.65 * (i + 1)) / maximo, height: Math.round(8 + (28 * (i + 1)) / maximo) }}
        />
      ))}
    </div>
  )
}

/** Tarjeta de una ruta en la lista del administrador (patrón Listado, RutasAdmin.dc.html). */
export function TarjetaDeRuta({ ruta }: { ruta: RutaResumen }) {
  return (
    <article className="flex flex-col gap-3 rounded-xl border border-border bg-surface px-5 py-[18px]">
      <div className="flex items-start justify-between gap-2.5">
        <h3 className="m-0 text-[17px] leading-snug">{ruta.nombre}</h3>
        <BadgeEstadoRuta estado={ruta.estado} />
      </div>
      <div className="text-sm text-muted">
        {ruta.codigo} · {ruta.departamento ?? rutasText.lista.sinDepartamento}
      </div>
      <p className="m-0 text-sm leading-normal">{ruta.descripcion ?? t.sinDescripcion}</p>
      <Peldanos niveles={ruta.niveles_count} archivada={ruta.estado === 'Archivada'} />
      <div className="mt-auto flex items-center justify-between gap-3 border-t border-border pt-2.5">
        <div className="text-sm tabular-nums">
          <b>{ruta.niveles_count}</b> {t.nivelPalabra(ruta.niveles_count)} · <b>{ruta.inscritos_count}</b> {t.participantePalabra(ruta.inscritos_count)}
        </div>
        <Link
          to={`/rutas/${ruta.id}`}
          aria-label={t.abrirRuta(ruta.nombre)}
          className="inline-flex h-9 items-center rounded-lg border border-border bg-surface px-3.5 text-sm font-semibold text-text no-underline hover:bg-soft"
        >
          {t.abrir}
        </Link>
      </div>
    </article>
  )
}
