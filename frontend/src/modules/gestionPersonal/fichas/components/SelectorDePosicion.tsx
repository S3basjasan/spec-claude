import { useMemo } from 'react'
import { Combobox } from '../../../../shared/ui/Combobox'
import { SearchField } from '../../../../shared/ui/SearchField'
import type { PosicionElegible } from '../api'
import { fichasText } from '../text'

const text = fichasText.entry

interface SelectorDePosicionProps {
  posiciones: PosicionElegible[]
  total: number
  /** Posición elegida, o null. */
  valor: number | null
  onChange: (id: number | null) => void
  busqueda: string
  onBusquedaChange: (texto: string) => void
  error?: string
}

/** Etiqueta única de una posición: puesto y unidad (la posición se identifica por su id). */
function etiquetaDePosicion(posicion: PosicionElegible): string {
  return posicion.unidad ? `${posicion.puesto} · ${posicion.unidad}` : posicion.puesto
}

/** Buscador remoto más selector con búsqueda; muestra puesto, unidad y jefatura de la elegida en solo lectura. */
export function SelectorDePosicion({ posiciones, total, valor, onChange, busqueda, onBusquedaChange, error }: SelectorDePosicionProps) {
  const opciones = useMemo(
    () =>
      posiciones.map((posicion) => ({
        value: String(posicion.id),
        label: `${etiquetaDePosicion(posicion)} (${posicion.id})`,
        description: posicion.jefatura ? `Jefatura: ${posicion.jefatura}` : undefined,
      })),
    [posiciones],
  )
  const elegida = posiciones.find((posicion) => posicion.id === valor) ?? null

  return (
    <div className="flex flex-col gap-3">
      <SearchField
        id="busqueda-posicion"
        label={text.positionSearch}
        placeholder={text.positionSearchPlaceholder}
        value={busqueda}
        onChange={(evento) => onBusquedaChange(evento.target.value)}
        maxLength={50}
      />
      <Combobox
        id="posicion"
        label={text.position}
        options={opciones}
        value={valor === null ? null : String(valor)}
        onChange={(nuevo) => onChange(nuevo === null ? null : Number(nuevo))}
        placeholder={text.positionPlaceholder}
        emptyText={text.noPositions}
        error={error}
      />
      <p className="text-[13px] text-muted">
        {total > posiciones.length ? text.positionMore(posiciones.length, total) : text.positionHint}
      </p>

      {elegida && (
        <dl aria-label={text.derived} className="grid grid-cols-1 gap-3 rounded-lg border border-border bg-soft p-3.5 sm:grid-cols-3">
          <div className="flex flex-col gap-0.5">
            <dt className="text-[13px] font-semibold text-muted">{text.derivedPosition}</dt>
            <dd className="text-[15px]">{elegida.puesto}</dd>
          </div>
          <div className="flex flex-col gap-0.5">
            <dt className="text-[13px] font-semibold text-muted">{text.derivedUnit}</dt>
            <dd className="text-[15px]">{elegida.unidad ?? fichasText.notRegistered}</dd>
          </div>
          <div className="flex flex-col gap-0.5">
            <dt className="text-[13px] font-semibold text-muted">{text.derivedBoss}</dt>
            <dd className={`text-[15px] ${elegida.jefatura ? '' : 'text-muted'}`}>{elegida.jefatura ?? fichasText.notRegistered}</dd>
          </div>
        </dl>
      )}
    </div>
  )
}
