import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { Parametros, ReglaFija, TipoPolitica } from '../api'
import { usePoliticaAplicable } from '../hooks/usePoliticas'
import { politicasText } from '../text'
import { CondicionesFijas } from './CondicionesFijas'
import { ResumenDeParametros } from './ResumenDeParametros'

const text = politicasText.applicable

function momentoLegible(iso: string): string {
  const valor = new Date(iso)
  return Number.isNaN(valor.getTime()) ? iso : valor.toLocaleString('es-EC', { dateStyle: 'medium', timeStyle: 'short' })
}

export interface CondicionesConservadas {
  numero: number
  parametros: Parametros
  reglas_fijas?: ReglaFija[]
}

interface CondicionesAplicablesProps {
  tipo: TipoPolitica
  /**
   * Copia de las condiciones conservadas con una solicitud ya presentada. Si se envía, se muestra tal cual y no se
   * consulta la política vigente (la solicitud conserva la versión con la que se presentó).
   */
  condicionesConservadas?: CondicionesConservadas
}

/**
 * Condiciones aplicables a un tipo de política para los procesos consumidores (novedades, movimientos, offboarding).
 * Distingue política sin configurar, política que no admite nuevas solicitudes y una falla temporal; una falla nunca
 * se presenta como ausencia de requisitos. No da acceso al panel administrativo.
 */
export function CondicionesAplicables({ tipo, condicionesConservadas }: CondicionesAplicablesProps) {
  const consulta = usePoliticaAplicable(tipo, condicionesConservadas === undefined)
  const nombre = politicasText.tipos[tipo]

  if (condicionesConservadas) {
    return (
      <section aria-label={`${text.title}: ${nombre}`} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
        <h2 className="text-base font-bold">{text.kept}</h2>
        <p className="text-sm text-muted">{text.version(condicionesConservadas.numero)}</p>
        <ResumenDeParametros parametros={condicionesConservadas.parametros} label={text.requirements} />
        <CondicionesFijas reglas={condicionesConservadas.reglas_fijas ?? []} />
      </section>
    )
  }

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={text.loading}>
        <Skeleton className="h-24" />
      </div>
    )
  }

  if (consulta.isError) {
    return (
      <div role="alert" className="flex flex-col items-start gap-2 rounded-xl border border-border bg-surface p-4">
        <p className="font-semibold text-danger">{text.errorTitle}</p>
        <p className="text-sm">{text.errorDescription}</p>
        <Button variant="secondary" onClick={() => void consulta.refetch()}>
          {politicasText.retry}
        </Button>
      </div>
    )
  }

  const politica = consulta.data

  if (politica.situacion === 'SinConfigurar') {
    return (
      <Alert tone="neutral">
        <p className="font-semibold">{text.notConfigured}</p>
        <p>{text.notConfiguredDescription}</p>
      </Alert>
    )
  }

  return (
    <section aria-label={`${text.title}: ${nombre}`} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
      <h2 className="text-base font-bold">{text.title}</h2>
      <p className="text-sm text-muted">
        {text.version(politica.numero ?? 0)}
        {politica.inicio_vigencia && ` · ${text.since(momentoLegible(politica.inicio_vigencia))}`}
      </p>
      {politica.situacion === 'NoAdmiteSolicitudes' && (
        <Alert tone="neutral">
          <p className="font-semibold">{text.notAccepting}</p>
          <p>{text.notAcceptingDescription}</p>
        </Alert>
      )}
      {politica.parametros && <ResumenDeParametros parametros={politica.parametros} label={text.requirements} />}
      <CondicionesFijas reglas={politica.reglas_fijas} />
    </section>
  )
}
