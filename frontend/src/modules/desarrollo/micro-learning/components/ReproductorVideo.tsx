import { useCallback, useEffect, useRef, useState } from 'react'
import { IconPlayerPause, IconPlayerPlay, IconVideo } from '../../../../shared/ui/icons'
import { microText } from '../text'
import { formatearSegundos } from '../tiempo'
import { resolverFuenteVideo } from '../videoUrl'

interface ReproductorVideoProps {
  titulo: string
  duracionSegundos: number
  /** Última posición guardada (0 si no hay). */
  posicionInicial: number
  /** `previa`: el Administrador ve el reproductor sin que se guarde nada. */
  modo: 'real' | 'previa'
  completado?: boolean
  /** Se invoca con la posición para guardarla (cada 10 s mientras reproduce, al pausar y al salir). */
  onPosicion?: (segundos: number) => void
}

const INTERVALO_GUARDADO_MS = 10_000

/**
 * Reproduce el video real cuando la URL es de YouTube, Vimeo o un archivo de video; en otro caso (o sin URL) usa el
 * marcador de posición. Los videos incrustados no informan su posición: el colaborador usa «Marcar como completado».
 */
export function ReproductorVideo(props: ReproductorVideoProps & { url?: string | null }) {
  const { url, ...resto } = props
  const fuente = resolverFuenteVideo(url)

  if (fuente?.tipo === 'incrustado') {
    return (
      <iframe
        title={resto.titulo}
        src={fuente.src}
        className="aspect-video w-full rounded-xl border border-border bg-black"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
        allowFullScreen
        referrerPolicy="strict-origin-when-cross-origin"
      />
    )
  }
  if (fuente?.tipo === 'archivo') {
    return <ReproductorArchivo {...resto} src={fuente.src} />
  }

  return <ReproductorSimulado {...resto} />
}

function ReproductorArchivo({ titulo, posicionInicial, modo, completado = false, onPosicion, src }: ReproductorVideoProps & { src: string }) {
  const ultimoGuardado = useRef(posicionInicial)
  const guardar = (segundos: number) => {
    if (modo !== 'real' || completado || Math.abs(segundos - ultimoGuardado.current) < 1) return
    ultimoGuardado.current = segundos
    onPosicion?.(segundos)
  }

  return (
    <video
      controls
      preload="metadata"
      src={src}
      aria-label={titulo}
      className="aspect-video w-full rounded-xl border border-border bg-black"
      onLoadedMetadata={(evento) => {
        if (posicionInicial > 0) evento.currentTarget.currentTime = posicionInicial
      }}
      onPause={(evento) => guardar(Math.floor(evento.currentTarget.currentTime))}
      onTimeUpdate={(evento) => {
        const segundos = Math.floor(evento.currentTarget.currentTime)
        if (segundos - ultimoGuardado.current >= 10) guardar(segundos)
      }}
    />
  )
}

/**
 * Marcador de posición del reproductor de video (spec 4.7): simula la reproducción (avanza un segundo por segundo),
 * reanuda desde la última posición guardada y la guarda sin reintentos. No hospeda ni transmite el video.
 */
function ReproductorSimulado({ titulo, duracionSegundos, posicionInicial, modo, completado = false, onPosicion }: ReproductorVideoProps) {
  const t = microText.player
  const [posicion, setPosicion] = useState(Math.min(Math.max(0, posicionInicial), duracionSegundos))
  const [reproduciendo, setReproduciendo] = useState(false)
  const [iniciado, setIniciado] = useState(false)

  const activo = reproduciendo && posicion < duracionSegundos
  const ultima = useRef(posicion)
  const guardada = useRef(posicionInicial)
  const alGuardar = useRef(onPosicion)

  useEffect(() => {
    ultima.current = posicion
  }, [posicion])
  useEffect(() => {
    alGuardar.current = onPosicion
  }, [onPosicion])

  const guardar = useCallback(() => {
    if (modo !== 'real' || completado || ultima.current === guardada.current) return
    guardada.current = ultima.current
    alGuardar.current?.(ultima.current)
  }, [modo, completado])

  useEffect(() => {
    if (!activo) return
    const id = setInterval(() => {
      // La referencia se actualiza al instante para que un guardado en el mismo segundo lea la posición vigente.
      ultima.current = Math.min(ultima.current + 1, duracionSegundos)
      setPosicion(ultima.current)
    }, 1000)
    return () => clearInterval(id)
  }, [activo, duracionSegundos])

  useEffect(() => {
    if (!activo) return
    const id = setInterval(guardar, INTERVALO_GUARDADO_MS)
    return () => clearInterval(id)
  }, [activo, guardar])

  // Al pausar o terminar se guarda la posición alcanzada.
  useEffect(() => {
    if (!activo) guardar()
  }, [activo, guardar])

  // Al salir de la pantalla se guarda lo último.
  useEffect(() => guardar, [guardar])

  const hayPosicionGuardada = posicionInicial > 0 && !iniciado

  return (
    <div className="flex flex-col gap-3">
      <div
        role="img"
        aria-label={`${t.videoPlaceholder}: ${titulo}`}
        className="flex aspect-video w-full flex-col items-center justify-center gap-2 rounded-xl border border-border bg-neutralsoft text-neutral"
      >
        <IconVideo size={44} />
        <p className="text-sm font-semibold">{t.videoPlaceholder}</p>
        <p className="text-[13px]" aria-hidden="true">
          {formatearSegundos(posicion)} / {formatearSegundos(duracionSegundos)}
        </p>
      </div>

      {hayPosicionGuardada && <p className="text-sm text-muted">{t.resumeHint(formatearSegundos(posicionInicial))}</p>}

      <div className="flex flex-wrap items-center gap-3">
        <button
          type="button"
          className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-transparent bg-accent px-4 text-[15px] font-semibold text-onaccent hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-45"
          disabled={posicion >= duracionSegundos}
          onClick={() => {
            setIniciado(true)
            setReproduciendo((actual) => !actual)
          }}
        >
          {activo ? <IconPlayerPause size={18} /> : <IconPlayerPlay size={18} />}
          {activo ? t.pause : hayPosicionGuardada ? t.resumeFrom(formatearSegundos(posicionInicial)) : t.play}
        </button>
        <label className="flex min-w-48 flex-1 items-center gap-2 text-[13px] text-muted">
          <span className="sr-only">{t.positionLabel}</span>
          <input
            type="range"
            min={0}
            max={duracionSegundos}
            value={posicion}
            aria-label={t.positionLabel}
            aria-valuetext={`${formatearSegundos(posicion)} de ${formatearSegundos(duracionSegundos)}`}
            onChange={(evento) => setPosicion(Number(evento.target.value))}
            className="h-11 w-full accent-accent"
          />
        </label>
      </div>
    </div>
  )
}
