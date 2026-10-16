import { Alert } from '../../../../shared/ui/Alert'
import type { AvisosDnf as AvisosTipo } from '../api'
import { dnfText } from '../text'

const text = dnfText.admin.avisos

/** Avisos informativos de una DNF: competencias omitidas, sin evaluación y colaboradores excluidos. */
export function AvisosDnf({ avisos }: { avisos: AvisosTipo }) {
  if (avisos.competencias_omitidas === 0 && avisos.sin_evaluacion === 0 && avisos.excluidos.length === 0) return null

  return (
    <div className="flex flex-col gap-2">
      {avisos.competencias_omitidas > 0 && <Alert tone="neutral">{text.omitidas(avisos.competencias_omitidas)}</Alert>}
      {avisos.sin_evaluacion > 0 && <Alert tone="neutral">{text.sinEvaluacion(avisos.sin_evaluacion)}</Alert>}
      {avisos.excluidos.length > 0 && (
        <Alert tone="neutral">
          <p>{text.excluidos(avisos.excluidos.length)}</p>
          <ul className="mt-1 list-disc pl-5">
            {avisos.excluidos.map((excluido) => (
              <li key={excluido.colaborador_id}>
                {excluido.nombre} — {dnfText.motivoExclusion[excluido.motivo] ?? excluido.motivo}
              </li>
            ))}
          </ul>
        </Alert>
      )}
    </div>
  )
}
