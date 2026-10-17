import { Badge } from '../../../../shared/ui/Badge'
import { IconClock } from '../../../../shared/ui/icons'
import type { FilaMicro } from '../api'
import { BORDE_TIPO, VARIANTE_ESTADO } from '../estilosTipo'
import { microText } from '../text'
import { formatearMinutos } from '../tiempo'
import { EtiquetaTipo } from './EtiquetaTipo'
import { MiniaturaMicro } from './MiniaturaMicro'

export interface AccionesTarjeta {
  onVistaPrevia: (micro: FilaMicro) => void
  onEditar: (micro: FilaMicro) => void
  onPublicar: (micro: FilaMicro) => void
  onArchivar: (micro: FilaMicro) => void
  onReactivar: (micro: FilaMicro) => void
  onEliminar: (micro: FilaMicro) => void
  onHistorial: (micro: FilaMicro) => void
}

interface TarjetaMicroProps extends AccionesTarjeta {
  micro: FilaMicro
  /** Desactiva las acciones mientras otra operación está en curso. */
  ocupado?: boolean
}

const BOTON = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-border bg-surface px-3 text-[14px] font-semibold text-text hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45'
const BOTON_PRIMARIO = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-accent px-3 text-[14px] font-semibold text-onaccent hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-45'
const BOTON_PELIGRO = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-border bg-surface px-3 text-[14px] font-semibold text-danger hover:bg-dangersoft disabled:cursor-not-allowed disabled:opacity-45'

/**
 * Tarjeta del repositorio (Administrador): miniatura, título, tipo con su color, competencia (la primera y «+N»),
 * duración, estado, completaciones y las acciones que el estado permite. Eliminar solo se ofrece sin progreso.
 */
export function TarjetaMicro({ micro, ocupado = false, ...acciones }: TarjetaMicroProps) {
  const t = microText.card
  const [primera, ...resto] = micro.competencias
  const puedeEliminar = micro.puede_eliminarse === true
  const accion = (nombre: string) => t.action(nombre, micro.titulo)

  return (
    <article className={`flex flex-col gap-3 rounded-xl border border-border border-t-4 bg-surface p-4 ${BORDE_TIPO[micro.tipo]}`} aria-label={micro.titulo}>
      <MiniaturaMicro tipo={micro.tipo} url={micro.miniatura_url} />

      <div className="flex flex-wrap items-center gap-2">
        <EtiquetaTipo tipo={micro.tipo} />
        <Badge variant={VARIANTE_ESTADO[micro.estado]}>{microText.estado[micro.estado]}</Badge>
      </div>

      <div className="flex flex-col gap-1">
        <p className="text-[13px] text-muted">{micro.codigo}</p>
        <h3 className="text-base font-bold leading-snug">{micro.titulo}</h3>
        <p className="text-[13px] font-semibold text-accent">{microText.aptoConocimientos}</p>
      </div>

      <dl className="flex flex-col gap-1 text-[13px] text-muted">
        <div className="flex flex-wrap items-center gap-1.5">
          <dt className="sr-only">Competencia</dt>
          <dd>
            {primera ? primera.nombre : microText.sinCompetencia}
            {resto.length > 0 && <span className="ml-1.5 font-semibold text-text">{microText.masCompetencias(resto.length)}</span>}
          </dd>
        </div>
        <div className="flex items-center gap-1.5">
          <IconClock size={14} />
          <dt className="sr-only">Duración</dt>
          <dd>{formatearMinutos(micro.duracion_minutos)}</dd>
        </div>
        <div>
          <dt className="sr-only">Completaciones</dt>
          <dd>{microText.completaciones(micro.completaciones ?? 0)}</dd>
        </div>
      </dl>

      {!puedeEliminar && micro.estado !== 'Archivado' && <p className="text-[13px] text-muted">{t.onlyArchive}</p>}

      <div role="group" aria-label={t.actionsLabel(micro.titulo)} className="mt-auto flex flex-wrap gap-2">
        <button type="button" className={BOTON} disabled={ocupado} aria-label={accion(t.preview)} onClick={() => acciones.onVistaPrevia(micro)}>
          {t.preview}
        </button>
        {micro.estado !== 'Archivado' && (
          <button type="button" className={BOTON} disabled={ocupado} aria-label={accion(t.edit)} onClick={() => acciones.onEditar(micro)}>
            {t.edit}
          </button>
        )}
        {micro.estado === 'Borrador' && (
          <button type="button" className={BOTON_PRIMARIO} disabled={ocupado} aria-label={accion(t.publish)} onClick={() => acciones.onPublicar(micro)}>
            {t.publish}
          </button>
        )}
        {micro.estado === 'Publicado' && (
          <button type="button" className={BOTON} disabled={ocupado} aria-label={accion(t.archive)} onClick={() => acciones.onArchivar(micro)}>
            {t.archive}
          </button>
        )}
        {micro.estado === 'Archivado' && (
          <button type="button" className={BOTON_PRIMARIO} disabled={ocupado} aria-label={accion(t.reactivate)} onClick={() => acciones.onReactivar(micro)}>
            {t.reactivate}
          </button>
        )}
        <button type="button" className={BOTON} disabled={ocupado} aria-label={accion(t.history)} onClick={() => acciones.onHistorial(micro)}>
          {t.history}
        </button>
        {puedeEliminar && (
          <button type="button" className={BOTON_PELIGRO} disabled={ocupado} aria-label={accion(t.remove)} onClick={() => acciones.onEliminar(micro)}>
            {t.remove}
          </button>
        )}
      </div>
    </article>
  )
}
