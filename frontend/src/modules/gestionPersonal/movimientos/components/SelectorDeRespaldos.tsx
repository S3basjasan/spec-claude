import { Alert } from '../../../../shared/ui/Alert'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { Respaldo } from '../api'
import { movimientosText } from '../text'

const text = movimientosText.novelty

interface SelectorDeRespaldosProps {
  respaldos: Respaldo[] | undefined
  cargando: boolean
  fallo: boolean
  elegidos: number[]
  onChange: (ids: number[]) => void
  exigido: boolean
  error?: string
}

/** Respaldos referenciados (nunca copiados): documentos ya cargados y accesibles para quien presenta. */
export function SelectorDeRespaldos({ respaldos, cargando, fallo, elegidos, onChange, exigido, error }: SelectorDeRespaldosProps) {
  const alternar = (id: number) => onChange(elegidos.includes(id) ? elegidos.filter((e) => e !== id) : [...elegidos, id])

  return (
    <fieldset className="flex flex-col gap-2" aria-describedby="respaldos-ayuda">
      <legend className="text-sm font-semibold">{text.supports}</legend>
      <p id="respaldos-ayuda" className="text-[13px] text-muted">
        {exigido ? text.supportsRequired : text.supportsOptional}
      </p>

      {cargando && <Skeleton className="h-10" />}
      {fallo && <Alert>{text.supportsError}</Alert>}
      {!cargando && !fallo && respaldos?.length === 0 && <p className="text-sm text-muted">{text.supportsNone}</p>}

      {respaldos?.map((respaldo) => (
        <label key={respaldo.id} className="flex min-h-11 items-center gap-2 rounded-lg border border-border px-3 text-sm">
          <input type="checkbox" className="size-4" checked={elegidos.includes(respaldo.id)} onChange={() => alternar(respaldo.id)} />
          <span>{respaldo.titulo}</span>
        </label>
      ))}

      {error && (
        <p role="alert" className="text-[13px] text-danger">
          {error}
        </p>
      )}
    </fieldset>
  )
}
