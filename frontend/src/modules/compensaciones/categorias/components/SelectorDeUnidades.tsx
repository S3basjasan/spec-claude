import { Button } from '../../../../shared/ui/Button'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { UnidadDisponible } from '../api'
import { categoriasReconocimientoText } from '../text'

const text = categoriasReconocimientoText.units

export interface SeleccionDeUnidades {
  unidades_ids: number[]
  /** «Todas las unidades»: el servidor las resuelve a las activas del momento de guardar. */
  todas: boolean
}

interface SelectorDeUnidadesProps {
  /** Unidades activas que se pueden elegir; `undefined` mientras cargan. */
  disponibles: UnidadDisponible[] | undefined
  cargando: boolean
  fallo: boolean
  seleccion: SeleccionDeUnidades
  onCambiar: (siguiente: SeleccionDeUnidades) => void
  onReintentar: () => void
  error?: string
  /** Nombres de unidades asignadas que ya no están activas; se quitan al guardar. */
  inactivas?: string[]
  disabled?: boolean
}

/** Casilla «Todas las unidades» y una casilla por unidad activa; muestra el alcance en unidades de la selección. */
export function SelectorDeUnidades({
  disponibles,
  cargando,
  fallo,
  seleccion,
  onCambiar,
  onReintentar,
  error,
  inactivas = [],
  disabled = false,
}: SelectorDeUnidadesProps) {
  const errorId = 'categoria-unidades-error'
  const ayudaId = 'categoria-unidades-ayuda'
  const cantidad = seleccion.todas ? (disponibles?.length ?? 0) : seleccion.unidades_ids.length

  const alternarTodas = (marcada: boolean) => {
    onCambiar(marcada ? { unidades_ids: (disponibles ?? []).map((unidad) => unidad.id), todas: true } : { unidades_ids: [], todas: false })
  }

  const alternarUnidad = (id: number, marcada: boolean) => {
    const siguientes = marcada ? [...seleccion.unidades_ids, id] : seleccion.unidades_ids.filter((actual) => actual !== id)
    onCambiar({ unidades_ids: siguientes, todas: false })
  }

  let cuerpo
  if (cargando) {
    cuerpo = (
      <div role="status" aria-label={text.loading} className="flex flex-col gap-2">
        <Skeleton className="h-6" />
        <Skeleton className="h-6" />
      </div>
    )
  } else if (fallo) {
    cuerpo = (
      <div className="flex flex-wrap items-center gap-3 text-sm text-danger" role="alert">
        <span>{text.error}</span>
        <Button variant="secondary" className="min-h-10 px-3 text-sm" onClick={onReintentar}>
          {text.retry}
        </Button>
      </div>
    )
  } else if ((disponibles ?? []).length === 0) {
    cuerpo = <p className="text-sm text-muted">{text.empty}</p>
  } else {
    cuerpo = (
      <div className="flex flex-col gap-1">
        <label className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 text-[15px] font-semibold focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent">
          <input
            type="checkbox"
            className="size-5 shrink-0 accent-accent"
            checked={seleccion.todas}
            disabled={disabled}
            onChange={(evento) => alternarTodas(evento.target.checked)}
          />
          {text.all}
        </label>
        <ul className="m-0 flex list-none flex-col gap-1 p-0">
          {(disponibles ?? []).map((unidad) => (
            <li key={unidad.id}>
              <label className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 text-[15px] focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent">
                <input
                  type="checkbox"
                  className="size-5 shrink-0 accent-accent"
                  checked={seleccion.todas || seleccion.unidades_ids.includes(unidad.id)}
                  disabled={disabled}
                  onChange={(evento) => alternarUnidad(unidad.id, evento.target.checked)}
                />
                {unidad.nombre}
              </label>
            </li>
          ))}
        </ul>
      </div>
    )
  }

  return (
    <fieldset className="m-0 flex flex-col gap-1.5 border-0 p-0" aria-describedby={error ? `${ayudaId} ${errorId}` : ayudaId}>
      <legend className="mb-1 text-[15px] font-semibold">{text.legend}</legend>
      <p id={ayudaId} className="text-[13px] text-muted">
        {text.hint}
      </p>
      {cuerpo}
      {inactivas.length > 0 && <p className="text-[13px] text-muted">{text.inactiveNote(inactivas.join(', '))}</p>}
      {error && (
        <p id={errorId} role="alert" className="text-[13px] text-danger">
          {error}
        </p>
      )}
      {!cargando && !fallo && (
        <p aria-live="polite" className="text-[13px] font-semibold text-muted">
          {cantidad === 0 ? text.scopeNobody : text.scope(cantidad)}
        </p>
      )}
    </fieldset>
  )
}
