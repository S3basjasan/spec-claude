import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { IconArrowDown, IconArrowUp, IconPlus, IconTrash } from '../../../../shared/ui/icons'
import { TextField } from '../../../../shared/ui/TextField'
import { MAX_ENUNCIADO, MAX_OPCIONES, MAX_PREGUNTAS, MAX_TEXTO_OPCION, MIN_OPCIONES, type PreguntaBorrador } from '../api'
import { microText } from '../text'
import { preguntaNueva, type ErroresMicro } from '../validation'

interface ConstructorDeQuizProps {
  preguntas: PreguntaBorrador[]
  onChange: (preguntas: PreguntaBorrador[]) => void
  errores?: ErroresMicro
  /** Solo lectura: el quiz ya tiene intentos terminados. */
  bloqueado?: boolean
}

const LETRAS = ['A', 'B', 'C', 'D', 'E']
const BOTON_ICONO = 'inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-muted hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45'

/**
 * Constructor de preguntas de opción múltiple (spec 4.2): agregar, quitar y reordenar preguntas y opciones y marcar
 * la correcta. De 1 a 20 preguntas y de 2 a 5 opciones por pregunta con exactamente una correcta.
 */
export function ConstructorDeQuiz({ preguntas, onChange, errores = {}, bloqueado = false }: ConstructorDeQuizProps) {
  const t = microText.builder
  const [aviso, setAviso] = useState('')

  const cambiarPregunta = (i: number, cambios: Partial<PreguntaBorrador>) =>
    onChange(preguntas.map((p, k) => (k === i ? { ...p, ...cambios } : p)))

  const mover = (i: number, delta: -1 | 1) => {
    const destino = i + delta
    if (destino < 0 || destino >= preguntas.length) return
    const copia = [...preguntas]
    ;[copia[i], copia[destino]] = [copia[destino], copia[i]]
    onChange(copia)
    setAviso(t.reordered(i + 1, destino + 1))
  }

  const cambiarOpcion = (i: number, j: number, cambios: Partial<PreguntaBorrador['opciones'][number]>) =>
    cambiarPregunta(i, { opciones: preguntas[i].opciones.map((o, k) => (k === j ? { ...o, ...cambios } : o)) })

  const marcarCorrecta = (i: number, j: number) =>
    cambiarPregunta(i, { opciones: preguntas[i].opciones.map((o, k) => ({ ...o, es_correcta: k === j })) })

  const quitarOpcion = (i: number, j: number) => {
    const opciones = preguntas[i].opciones.filter((_, k) => k !== j)
    // Si se quita la correcta, la primera que queda pasa a serlo para no dejar la pregunta sin respuesta.
    if (!opciones.some((o) => o.es_correcta) && opciones.length > 0) opciones[0] = { ...opciones[0], es_correcta: true }
    cambiarPregunta(i, { opciones })
  }

  return (
    <fieldset className="flex flex-col gap-3" disabled={bloqueado}>
      <legend className="mb-1 text-sm font-semibold">{t.legend(preguntas.length)}</legend>
      <p className="text-[13px] text-muted">{t.hint}</p>
      {bloqueado && <Alert tone="neutral">{microText.form.quizHasAttempts}</Alert>}
      {errores.preguntas && <p className="text-[13px] text-danger">{errores.preguntas}</p>}

      {preguntas.length === 0 && <p className="rounded-lg border border-dashed border-border p-4 text-sm text-muted">{t.empty}</p>}

      {preguntas.map((pregunta, i) => {
        const n = i + 1
        return (
          <section key={i} aria-label={t.question(n)} className="flex flex-col gap-3 rounded-xl border border-border bg-bg p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h4 className="text-[15px] font-bold">{t.question(n)}</h4>
              <div className="flex items-center">
                <button type="button" className={BOTON_ICONO} aria-label={t.moveUp(n)} disabled={i === 0 || bloqueado} onClick={() => mover(i, -1)}>
                  <IconArrowUp size={18} />
                </button>
                <button type="button" className={BOTON_ICONO} aria-label={t.moveDown(n)} disabled={i === preguntas.length - 1 || bloqueado} onClick={() => mover(i, 1)}>
                  <IconArrowDown size={18} />
                </button>
                <button type="button" className={`${BOTON_ICONO} text-danger`} aria-label={t.removeQuestion(n)} disabled={bloqueado} onClick={() => onChange(preguntas.filter((_, k) => k !== i))}>
                  <IconTrash size={18} />
                </button>
              </div>
            </div>

            <TextField
              id={`pregunta-${i}-enunciado`}
              label={`${t.statement} de la pregunta ${n}`}
              value={pregunta.enunciado}
              maxLength={MAX_ENUNCIADO}
              error={errores[`preguntas.${i}.enunciado`]}
              hint={t.counter(pregunta.enunciado.length, MAX_ENUNCIADO)}
              onChange={(evento) => cambiarPregunta(i, { enunciado: evento.target.value })}
            />

            <fieldset className="flex flex-col gap-2">
              <legend className="mb-1 text-[13px] font-semibold text-muted">{t.options(pregunta.opciones.length)}</legend>
              {pregunta.opciones.map((opcion, j) => (
                <div key={j} className="flex items-start gap-2">
                  <input
                    type="radio"
                    name={`pregunta-${i}-correcta`}
                    className="mt-3.5 size-4 shrink-0 accent-accent"
                    checked={opcion.es_correcta}
                    aria-label={t.correct(n, LETRAS[j])}
                    onChange={() => marcarCorrecta(i, j)}
                  />
                  <span className="mt-2.5 w-5 shrink-0 text-sm font-bold text-muted" aria-hidden="true">
                    {LETRAS[j]}
                  </span>
                  <div className="flex-1">
                    <TextField
                      id={`pregunta-${i}-opcion-${j}`}
                      label={t.optionText(n, LETRAS[j])}
                      hideLabel
                      value={opcion.texto}
                      maxLength={MAX_TEXTO_OPCION}
                      placeholder={t.option(LETRAS[j])}
                      error={errores[`preguntas.${i}.opciones.${j}.texto`]}
                      onChange={(evento) => cambiarOpcion(i, j, { texto: evento.target.value })}
                    />
                  </div>
                  <button
                    type="button"
                    className={BOTON_ICONO}
                    aria-label={t.removeOption(n, LETRAS[j])}
                    disabled={pregunta.opciones.length <= MIN_OPCIONES || bloqueado}
                    onClick={() => quitarOpcion(i, j)}
                  >
                    <IconTrash size={18} />
                  </button>
                </div>
              ))}
              {(errores[`preguntas.${i}.opciones`] || errores[`preguntas.${i}.correcta`]) && (
                <p className="text-[13px] text-danger">{errores[`preguntas.${i}.opciones`] ?? errores[`preguntas.${i}.correcta`]}</p>
              )}
              <button
                type="button"
                className="inline-flex min-h-11 items-center gap-1.5 self-start rounded-lg border border-border bg-surface px-3 text-[14px] font-semibold hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45"
                disabled={pregunta.opciones.length >= MAX_OPCIONES || bloqueado}
                onClick={() => cambiarPregunta(i, { opciones: [...pregunta.opciones, { texto: '', es_correcta: false }] })}
              >
                <IconPlus size={16} />
                {t.addOption}
              </button>
            </fieldset>
          </section>
        )
      })}

      <div className="flex flex-wrap items-center gap-3">
        <button
          type="button"
          className="inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-border bg-surface px-4 text-[15px] font-semibold hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45"
          disabled={preguntas.length >= MAX_PREGUNTAS || bloqueado}
          onClick={() => onChange([...preguntas, preguntaNueva()])}
        >
          <IconPlus size={16} />
          {t.addQuestion}
        </button>
        {preguntas.length >= MAX_PREGUNTAS && <span className="text-[13px] text-muted">{t.maxQuestions}</span>}
      </div>

      <p role="status" className="sr-only">
        {aviso}
      </p>
    </fieldset>
  )
}
