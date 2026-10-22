import { Alert } from '../../../../shared/ui/Alert'
import type { Aviso } from '../api'
import { adecuacionText } from '../text'

/** Avisos informativos del diagnóstico: requisitos sin evaluación, elementos inactivos omitidos y fuentes caídas. */
export function AvisosDelDiagnostico({ avisos, fuentesNoDisponibles }: { avisos: Aviso[]; fuentesNoDisponibles: string[] }) {
  // «Conocimientos desactivados» y «experiencia no evaluada» se explican dentro de su propio panel.
  const visibles = avisos.filter((a) => a.tipo !== 'conocimientos_desactivados' && a.tipo !== 'experiencia_no_evaluada')

  if (visibles.length === 0 && fuentesNoDisponibles.length === 0) return null

  return (
    <div className="flex flex-col gap-2">
      {visibles.map((a) => (
        <Alert key={a.tipo} tone="neutral">
          {a.tipo === 'sin_evaluacion' ? adecuacionText.avisos.sinEvaluacion(a.cantidad) : a.mensaje}
        </Alert>
      ))}
      {fuentesNoDisponibles.length > 0 && <Alert tone="neutral">{adecuacionText.errores.fuentes}</Alert>}
    </div>
  )
}
