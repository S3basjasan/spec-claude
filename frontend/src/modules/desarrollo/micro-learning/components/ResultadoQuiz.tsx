import { Button } from '../../../../shared/ui/Button'
import { microText } from '../text'

interface ResultadoQuizProps {
  correctas: number
  total: number
  onReintentar: () => void
  /** Ausente en la vista previa del Administrador. */
  onVolver?: () => void
  reintentando?: boolean
  error?: string
}

/** Puntaje final «N de M» (sin aprobado ni reprobado: el quiz no tiene puntaje mínimo) y opción de repetir. */
export function ResultadoQuiz({ correctas, total, onReintentar, onVolver, reintentando = false, error }: ResultadoQuizProps) {
  const t = microText.quiz

  return (
    <section aria-label={t.result} className="flex flex-col items-center gap-3 rounded-xl border border-border bg-surface p-6 text-center">
      <h2 className="text-lg font-bold">{t.result}</h2>
      <p className="text-4xl font-bold text-accent" data-testid="puntaje">
        {t.resultScore(correctas, total)}
      </p>
      <p className="text-sm text-muted">{t.resultDetail(correctas, total)}</p>
      <p className="max-w-md text-sm">{t.resultMessage}</p>
      {error && (
        <p role="alert" className="text-[13px] text-danger">
          {error}
        </p>
      )}
      <div className="flex flex-wrap justify-center gap-3">
        <Button onClick={onReintentar} loading={reintentando}>
          {t.retry}
        </Button>
        {onVolver && (
          <Button variant="secondary" onClick={onVolver}>
            {t.backToLearning}
          </Button>
        )}
      </div>
    </section>
  )
}
