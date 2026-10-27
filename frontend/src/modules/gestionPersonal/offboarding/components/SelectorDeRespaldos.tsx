import { Alert } from '../../../../shared/ui/Alert'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { Respaldo } from '../api'

interface SelectorDeRespaldosProps {
  titulo: string
  ayuda: string
  vacio: string
  errorDeCarga: string
  respaldos: Respaldo[] | undefined
  cargando: boolean
  fallo: boolean
  elegidos: number[]
  onChange: (ids: number[]) => void
  error?: string
}

/** Respaldos referenciados (nunca copiados): documentos ya cargados y accesibles para el Administrador. */
export function SelectorDeRespaldos({ titulo, ayuda, vacio, errorDeCarga, respaldos, cargando, fallo, elegidos, onChange, error }: SelectorDeRespaldosProps) {
  const alternar = (id: number) => onChange(elegidos.includes(id) ? elegidos.filter((e) => e !== id) : [...elegidos, id])

  return (
    <fieldset className="flex flex-col gap-2">
      <legend className="text-sm font-semibold">{titulo}</legend>
      <p className="text-[13px] text-muted">{ayuda}</p>

      {cargando && <Skeleton className="h-10" />}
      {fallo && <Alert>{errorDeCarga}</Alert>}
      {!cargando && !fallo && respaldos?.length === 0 && <p className="text-sm text-muted">{vacio}</p>}

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
