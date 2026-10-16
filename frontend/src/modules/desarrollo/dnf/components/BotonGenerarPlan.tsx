import { Link } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { rutaDelPlan, type PlanGenerado, type PlanReferencia } from '../api'
import { useGenerarPlan } from '../hooks/useGenerarPlan'
import { dnfText } from '../text'

const text = dnfText.admin.plan

interface BotonGenerarPlanProps {
  dnfId: number
  cantidadBrechas: number
  plan: PlanReferencia | null
  /** Se llama con la referencia del plan recién generado (el padre puede actualizar su copia local). */
  onGenerado?: (plan: PlanGenerado) => void
}

function mensajeDeError(error: unknown): string {
  const api = toApiError(error)
  if (api.status === 503) return text.noDisponible
  return api.message ?? dnfText.admin.error.generar
}

/**
 * «Generar Plan de Formación» (solo Administrador). Una DNF genera como máximo un borrador: queda deshabilitado, con su
 * motivo visible, si no tiene brechas o si ya generó un plan; en este último caso muestra el enlace al plan.
 */
export function BotonGenerarPlan({ dnfId, cantidadBrechas, plan, onGenerado }: BotonGenerarPlanProps) {
  const generar = useGenerarPlan()

  if (plan) {
    return (
      <div className="flex flex-col gap-2">
        <p className="text-sm" id={`plan-estado-${dnfId}`}>
          {text.motivoYaGenerado}
          {plan.generado_por && plan.generado_en ? ` ${text.generadoPor(plan.generado_por.nombre, dnfText.fecha(plan.generado_en))}` : ''}
        </p>
        <Link
          to={rutaDelPlan(plan.id)}
          className="inline-flex min-h-11 items-center justify-center self-start rounded-lg border border-border bg-surface px-5 text-[15px] font-semibold hover:bg-soft"
        >
          {text.verPlan}
        </Link>
      </div>
    )
  }

  const sinBrechas = cantidadBrechas === 0
  const idMotivo = `plan-motivo-${dnfId}`

  return (
    <div className="flex flex-col gap-2">
      <Button
        disabled={sinBrechas}
        aria-describedby={sinBrechas ? idMotivo : undefined}
        loading={generar.isPending}
        className="self-start"
        onClick={() =>
          generar.mutate(dnfId, {
            onSuccess: (generado) => onGenerado?.(generado),
          })
        }
      >
        {generar.isPending ? text.generando : text.boton}
      </Button>
      {sinBrechas && (
        <p id={idMotivo} className="text-[13px] text-muted">
          {text.motivoSinBrechas}
        </p>
      )}
      {generar.isError && <Alert>{mensajeDeError(generar.error)}</Alert>}
    </div>
  )
}
