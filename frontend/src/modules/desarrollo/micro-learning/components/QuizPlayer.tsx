import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Button } from '../../../../shared/ui/Button'
import { IconCheck, IconX } from '../../../../shared/ui/icons'
import type { PreguntaQuiz, RespuestaDada, ResultadoRespuesta } from '../api'
import { microText } from '../text'
import { ResultadoQuiz } from './ResultadoQuiz'

interface QuizPlayerProps {
  preguntas: PreguntaQuiz[]
  /** Respuestas ya dadas en el intento (para retomarlo). */
  respondidas?: RespuestaDada[]
  /** Registra la respuesta y devuelve la retroalimentación (en la vista previa se evalúa en el cliente). */
  onResponder: (preguntaId: number, opcionId: number) => Promise<ResultadoRespuesta>
  /** Empieza otro intento; el padre reinicia el componente con una `key` nueva. */
  onReintentar: () => Promise<void>
  onVolver?: () => void
}

/**
 * Quiz de una pregunta a la vez (spec 4.8): «Pregunta N de M», retroalimentación inmediata con la opción correcta,
 * puntaje final «N de M» y opción de repetir. El servidor es quien revela la correcta al responder.
 */
export function QuizPlayer({ preguntas, respondidas = [], onResponder, onReintentar, onVolver }: QuizPlayerProps) {
  const t = microText.quiz
  const [dadas, setDadas] = useState<RespuestaDada[]>(respondidas)
  const [elegida, setElegida] = useState<{ preguntaId: number; opcionId: number; resultado: ResultadoRespuesta } | null>(null)
  const [enviando, setEnviando] = useState(false)
  const [reintentando, setReintentando] = useState(false)
  const [error, setError] = useState<string>()
  const [finalizado, setFinalizado] = useState(respondidas.length >= preguntas.length && preguntas.length > 0)

  const total = preguntas.length
  const indice = Math.min(dadas.length, total - 1)
  const pregunta = preguntas[indice]
  const correctas = dadas.filter((r) => r.es_correcta).length

  const responder = async (opcionId: number) => {
    if (elegida || enviando || !pregunta) return
    setEnviando(true)
    setError(undefined)
    try {
      const resultado = await onResponder(pregunta.id, opcionId)
      setElegida({ preguntaId: pregunta.id, opcionId, resultado })
    } catch (e) {
      const api = toApiError(e)
      setError(api.status === 404 || api.code === 'intento_descartado' ? t.restartNeeded : t.answerFailed)
    } finally {
      setEnviando(false)
    }
  }

  const siguiente = () => {
    if (!elegida) return
    const nuevas = [...dadas, { pregunta_id: elegida.preguntaId, opcion_id: elegida.opcionId, es_correcta: elegida.resultado.es_correcta }]
    setDadas(nuevas)
    setElegida(null)
    if (nuevas.length >= total) setFinalizado(true)
  }

  const reintentar = async () => {
    setReintentando(true)
    setError(undefined)
    try {
      await onReintentar()
    } catch {
      setError(t.startFailed)
      setReintentando(false)
    }
  }

  if (total === 0) return <p className="text-sm text-muted">{microText.preview.noQuestions}</p>

  if (finalizado) {
    return <ResultadoQuiz correctas={correctas} total={total} onReintentar={() => void reintentar()} onVolver={onVolver} reintentando={reintentando} error={error} />
  }

  const respuestaDada = elegida?.resultado
  const esUltima = dadas.length + 1 >= total

  return (
    <section aria-label={microText.quiz.questionOf(indice + 1, total)} className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5">
      {respondidas.length > 0 && dadas.length === respondidas.length && <p className="text-sm text-muted">{t.resumeIntro(respondidas.length, total)}</p>}
      <p className="text-sm font-semibold text-muted" aria-live="polite">
        {t.questionOf(indice + 1, total)}
      </p>
      <h2 className="text-lg font-bold leading-snug">{pregunta.enunciado}</h2>

      <div role="group" aria-label={t.optionsLabel} className="flex flex-col gap-2">
        {pregunta.opciones.map((opcion, i) => {
          const esLaElegida = elegida?.opcionId === opcion.id
          const esLaCorrecta = respuestaDada?.opcion_correcta_id === opcion.id
          let estilo = 'border-border bg-surface hover:bg-soft'
          if (respuestaDada) {
            if (esLaCorrecta) estilo = 'border-accent bg-soft'
            else if (esLaElegida) estilo = 'border-danger bg-dangersoft'
            else estilo = 'border-border bg-surface opacity-70'
          }
          return (
            <button
              key={opcion.id}
              type="button"
              aria-pressed={esLaElegida}
              disabled={elegida !== null || enviando}
              onClick={() => void responder(opcion.id)}
              className={`flex min-h-11 items-center gap-3 rounded-lg border px-3 py-2 text-left text-[15px] disabled:cursor-default ${estilo}`}
            >
              <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-neutralsoft text-sm font-bold text-neutral" aria-hidden="true">
                {String.fromCharCode(65 + i)}
              </span>
              <span className="flex-1">{opcion.texto}</span>
              {respuestaDada && esLaCorrecta && (
                <span className="flex items-center gap-1 text-[13px] font-semibold text-accent">
                  <IconCheck size={16} />
                  {t.correctMark}
                </span>
              )}
              {respuestaDada && esLaElegida && !esLaCorrecta && (
                <span className="flex items-center gap-1 text-[13px] font-semibold text-danger">
                  <IconX size={16} />
                  {t.wrongMark}
                </span>
              )}
            </button>
          )
        })}
      </div>

      <div role="status" aria-live="polite" className="min-h-6 text-sm">
        {respuestaDada && (
          <p className={`font-semibold ${respuestaDada.es_correcta ? 'text-accent' : 'text-danger'}`}>
            {respuestaDada.es_correcta ? t.correct : t.incorrect}
            {!respuestaDada.es_correcta && respuestaDada.opcion_correcta_id !== null && (
              <span className="ml-1 font-normal text-text">{t.correctWas(pregunta.opciones.find((o) => o.id === respuestaDada.opcion_correcta_id)?.texto ?? '')}</span>
            )}
          </p>
        )}
      </div>

      {error && (
        <p role="alert" className="text-[13px] text-danger">
          {error}
        </p>
      )}

      <div className="flex flex-wrap gap-3">
        <Button onClick={siguiente} disabled={elegida === null}>
          {esUltima ? t.finish : t.next}
        </Button>
        {onVolver && (
          <Button variant="secondary" onClick={onVolver}>
            {t.exit}
          </Button>
        )}
      </div>
    </section>
  )
}
