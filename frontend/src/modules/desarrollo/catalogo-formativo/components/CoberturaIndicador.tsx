import { useId } from 'react'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { Cobertura } from '../api'
import { catalogoText } from '../text'

interface CoberturaIndicadorProps {
  /** `undefined` mientras carga. */
  cobertura: Cobertura | undefined
  error?: boolean
  /** Aplica el filtro "sin cobertura" en la matriz. */
  onVerSinCobertura: () => void
}

const text = catalogoText.coverage

/** Indicador de cobertura formativa: porcentaje, barra de progreso accesible y acceso a las competencias sin cobertura. */
export function CoberturaIndicador({ cobertura, error = false, onVerSinCobertura }: CoberturaIndicadorProps) {
  const titulo = useId()

  return (
    <section aria-labelledby={titulo} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
      <div>
        <h2 id={titulo} className="text-sm font-semibold text-muted">
          {text.title}
        </h2>
        <p className="text-[13px] text-muted">{text.description}</p>
      </div>

      {error ? (
        <p role="alert" className="text-sm text-danger">
          {text.errorTitle}
        </p>
      ) : cobertura === undefined ? (
        <Skeleton className="h-12" />
      ) : (
        <>
          <div className="flex flex-wrap items-center gap-4">
            <span className="text-[32px] font-bold leading-none tracking-tight">{cobertura.porcentaje}%</span>
            <div
              role="progressbar"
              aria-label={text.label}
              aria-valuenow={cobertura.porcentaje}
              aria-valuemin={0}
              aria-valuemax={100}
              className="h-2.5 min-w-40 flex-1 overflow-hidden rounded-full bg-border"
            >
              <div className="h-full rounded-full bg-accent" style={{ width: `${cobertura.porcentaje}%` }} />
            </div>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-2 text-[13px] text-muted">
            <span>{text.summary(cobertura.activas_cubiertas, cobertura.activas_totales)}</span>
            <button type="button" onClick={onVerSinCobertura} className="min-h-11 font-semibold text-accent underline">
              {text.seeUncovered}
            </button>
          </div>
        </>
      )}
    </section>
  )
}
