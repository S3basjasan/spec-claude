import type { Nivel } from '../api'
import { rutasText } from '../text'

const t = rutasText.miRuta

interface EscaleraColaboradorProps {
  niveles: Nivel[]
  actualId: number | null
  objetivoId: number | null
  siguienteId: number | null
  seleccionado: number | null
  onSeleccionar: (nivelId: number) => void
}

/** Escalera de «Mi ruta de carrera» (RutasColaborador.dc.html): nivel actual resaltado, objetivo marcado y siguiente indicado. */
export function EscaleraColaborador({ niveles, actualId, objetivoId, siguienteId, seleccionado, onSeleccionar }: EscaleraColaboradorProps) {
  const ordenActual = niveles.find((n) => n.id === actualId)?.orden ?? 0
  const dePeldano = niveles.map((nivel, indice) => ({ nivel, indice })).reverse()

  return (
    <ol aria-label={rutasText.escalera.aria} className="m-0 flex list-none flex-col gap-2.5 p-0">
      {dePeldano.map(({ nivel, indice }) => {
        const esActual = nivel.id === actualId
        const esObjetivo = nivel.id === objetivoId
        const esSiguiente = nivel.id === siguienteId
        const pasado = nivel.orden < ordenActual
        const elegido = nivel.id === seleccionado
        const etiquetas: string[] = []
        if (esActual) etiquetas.push(t.tagActual)
        if (esObjetivo) etiquetas.push(t.tagObjetivo)
        if (esSiguiente && !esObjetivo) etiquetas.push(t.tagSiguiente)

        return (
          <li key={nivel.id} style={{ marginLeft: Math.min(indice, 6) * 14 }} aria-current={esActual ? 'step' : undefined}>
            <button
              type="button"
              onClick={() => onSeleccionar(nivel.id)}
              aria-pressed={elegido}
              aria-label={`${rutasText.escalera.ver(nivel.orden, nivel.nombre)}${etiquetas.length > 0 ? `. ${etiquetas.join(', ')}` : ''}`}
              className={`flex min-h-11 w-full cursor-pointer flex-col gap-0.5 rounded-xl border-2 px-3.5 py-3 text-left text-text ${
                esActual ? 'border-accent bg-soft' : elegido ? 'border-accent bg-surface' : 'border-border bg-surface'
              } ${pasado ? 'opacity-70' : ''}`}
            >
              <span className="flex flex-wrap items-center gap-2 text-xs font-bold text-accent">
                {rutasText.escalera.nivel(nivel.orden)}
                {etiquetas.map((e) => (
                  <span key={e} className={`rounded-full px-2 py-px text-[11px] font-semibold ${e === t.tagObjetivo ? 'bg-warnsoft text-warn' : e === t.tagActual ? 'bg-accent text-onaccent' : 'bg-infosoft text-info'}`}>
                    {e}
                  </span>
                ))}
              </span>
              <span className="text-[15px] font-semibold leading-snug">{nivel.nombre}</span>
              <span className="text-xs text-muted">{nivel.puesto.codigo ?? '—'}</span>
            </button>
          </li>
        )
      })}
    </ol>
  )
}
