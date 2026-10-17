import { Link } from 'react-router-dom'
import { Badge } from '../../../../shared/ui/Badge'
import { IconClock, IconDownload } from '../../../../shared/ui/icons'
import type { FilaMicro } from '../api'
import { BORDE_TIPO } from '../estilosTipo'
import { microText } from '../text'
import { formatearFecha, formatearMinutos, formatearSegundos } from '../tiempo'
import { EtiquetaTipo } from './EtiquetaTipo'
import { MiniaturaMicro } from './MiniaturaMicro'

interface TarjetaAprendizajeProps {
  micro: FilaMicro
  /** `completado` muestra el certificado; `asignado` el botón Iniciar o Continuar. */
  pestana: 'asignado' | 'completado'
  onCertificado?: (micro: FilaMicro) => void
  descargando?: boolean
}

const BOTON_PRIMARIO = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-accent px-4 text-[15px] font-semibold text-onaccent hover:bg-accent-hover'
const BOTON = 'inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-4 text-[15px] font-semibold text-text hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45'

/**
 * Tarjeta del colaborador: miniatura, título, tipo, competencia, duración y estado. En «Asignado» ofrece Iniciar o
 * Continuar; en «Completado», Ver y la descarga del certificado.
 */
export function TarjetaAprendizaje({ micro, pestana, onCertificado, descargando = false }: TarjetaAprendizajeProps) {
  const t = microText.colab
  const completado = pestana === 'completado' || micro.progreso?.estado === 'Completado'
  const enProgreso = micro.progreso?.estado === 'EnProgreso'
  const competencia = micro.competencias[0]?.nombre ?? microText.sinCompetencia
  const puntaje = micro.ultimo_puntaje ?? micro.progreso?.ultimo_puntaje ?? null
  const posicion = micro.tipo === 'Video' && enProgreso ? (micro.progreso?.posicion_segundos ?? 0) : 0
  const accion = completado ? t.view : enProgreso ? t.resume : t.start
  const destino = `/micro-learning/${micro.id}`

  return (
    <article className={`flex flex-col gap-3 rounded-xl border border-border border-t-4 bg-surface p-4 ${BORDE_TIPO[micro.tipo]}`} aria-label={micro.titulo}>
      <MiniaturaMicro tipo={micro.tipo} url={micro.miniatura_url} />

      <div className="flex flex-wrap items-center gap-2">
        <EtiquetaTipo tipo={micro.tipo} />
        <Badge variant={completado ? 'default' : enProgreso ? 'warning' : 'neutral'}>
          {completado ? t.state.Completado : enProgreso ? t.state.EnProgreso : t.state.Nuevo}
        </Badge>
      </div>

      <div className="flex flex-col gap-1">
        <h3 className="text-base font-bold leading-snug">{micro.titulo}</h3>
        <p className="text-[13px] font-semibold text-accent">{microText.aptoConocimientos}</p>
      </div>

      <dl className="flex flex-col gap-1 text-[13px] text-muted">
        <div>
          <dt className="sr-only">Competencia</dt>
          <dd>{competencia}</dd>
        </div>
        <div className="flex items-center gap-1.5">
          <IconClock size={14} />
          <dt className="sr-only">Duración</dt>
          <dd>{formatearMinutos(micro.duracion_minutos)}</dd>
        </div>
        {micro.motivo && !completado && (
          <div>
            <dt className="sr-only">Motivo</dt>
            <dd>{t.reason[micro.motivo]}</dd>
          </div>
        )}
        {posicion > 0 && <div>{t.position(formatearSegundos(posicion))}</div>}
        {puntaje && <div>{t.scoreLabel(puntaje.correctas, puntaje.total)}</div>}
        {completado && micro.completado_en && <div>{t.doneOn(formatearFecha(micro.completado_en))}</div>}
      </dl>

      <div className="mt-auto flex flex-wrap gap-2">
        <Link to={destino} aria-label={t.openOf(accion, micro.titulo)} className={completado ? BOTON : BOTON_PRIMARIO}>
          {accion}
        </Link>
        {completado && onCertificado && (
          <button type="button" className={BOTON} disabled={descargando} aria-label={t.certificateOf(micro.titulo)} onClick={() => onCertificado(micro)}>
            <IconDownload size={16} />
            {t.certificate}
          </button>
        )}
      </div>
    </article>
  )
}
