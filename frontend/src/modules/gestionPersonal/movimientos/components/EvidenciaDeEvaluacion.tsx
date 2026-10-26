import { Alert } from '../../../../shared/ui/Alert'
import type { DetalleDeMovimiento } from '../api'
import { movimientosText } from '../text'

const text = movimientosText.detail

function momentoLegible(valor: unknown): string {
  if (typeof valor !== 'string') return movimientosText.none
  const fecha = new Date(valor)
  return Number.isNaN(fecha.getTime()) ? valor : fecha.toLocaleString('es-EC', { dateStyle: 'medium', timeStyle: 'short' })
}

/**
 * Evidencia de la evaluación frente al puesto destino. Separa «evaluación válida» de «no disponible» y «no
 * verificable»; nunca muestra un porcentaje ni un apto calculado: solo lo que informó el módulo evaluador.
 */
export function EvidenciaDeEvaluacion({ evaluacion }: { evaluacion: DetalleDeMovimiento['evaluacion'] }) {
  if (evaluacion === null) return <p className="text-sm text-muted">{text.evaluationNone}</p>

  if (evaluacion.estado !== 'Valida') {
    return (
      <Alert>
        <p className="font-semibold">{text.evaluationStates[evaluacion.estado]}</p>
        {evaluacion.motivo && <p>{evaluacion.motivo}</p>}
      </Alert>
    )
  }

  const contexto = evaluacion.contexto ?? {}
  const requisitos = Array.isArray(contexto.requisitos) ? (contexto.requisitos as unknown[]).filter((r): r is string => typeof r === 'string') : []

  return (
    <div className="flex flex-col gap-2 rounded-lg border border-border bg-surface p-3">
      <p className="font-semibold text-accent">{text.evaluationStates.Valida}</p>
      <dl className="grid gap-2 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-[13px] font-semibold text-muted">{text.evaluationResult}</dt>
          <dd>{typeof contexto.resultado === 'string' ? contexto.resultado : movimientosText.none}</dd>
        </div>
        <div>
          <dt className="text-[13px] font-semibold text-muted">{text.evaluationWhen}</dt>
          <dd>{momentoLegible(contexto.evaluada_en)}</dd>
        </div>
      </dl>
      <div>
        <p className="text-[13px] font-semibold text-muted">{text.evaluationRequirements}</p>
        <ul className="list-disc pl-5 text-sm">
          {requisitos.map((requisito) => (
            <li key={requisito}>{requisito}</li>
          ))}
        </ul>
      </div>
    </div>
  )
}
