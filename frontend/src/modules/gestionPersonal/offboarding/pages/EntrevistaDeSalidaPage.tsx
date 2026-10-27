import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconClock } from '../../../../shared/ui/icons'
import { Modal } from '../../../../shared/ui/Modal'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import type { MiEntrevista, RespuestasDeEntrevista } from '../api'
import { problemaDeSalida, type ProblemaDeSalida } from '../errores'
import { useClaveDeOperacion } from '../hooks/useClaveDeOperacion'
import { useGuardarMiEntrevista, useMiEntrevista, useResolverMiEntrevista } from '../hooks/useOffboarding'
import { offboardingText } from '../text'
import { TEXTO_ABIERTO_MAXIMO } from '../validation'

const text = offboardingText.interviewForm

function Cerrada({ entrevista }: { entrevista: MiEntrevista }) {
  const { cuestionario, respuestas } = entrevista

  return (
    <div className="flex flex-col gap-4">
      <Alert tone="neutral">{entrevista.estado === 'Completada' ? text.closedCompleted : text.closedNotDone}</Alert>
      {entrevista.estado === 'Completada' && (
        <section aria-label={offboardingText.interview.answersTitle} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
          <dl className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            {Object.entries(cuestionario.dimensiones).map(([clave, dimension]) => (
              <div key={clave} className="flex flex-col gap-0.5 rounded-lg border border-border p-3">
                <dt className="text-[13px] font-semibold text-muted">{dimension.nombre}</dt>
                <dd className="text-[15px]">{respuestas.valoraciones[clave] ? `${respuestas.valoraciones[clave]} · ${cuestionario.escala[String(respuestas.valoraciones[clave])]}` : offboardingText.interview.notAnswered}</dd>
              </div>
            ))}
          </dl>
          <p className="text-sm">
            <span className="font-semibold">{offboardingText.interview.declaredReasons}: </span>
            {respuestas.categorias.length === 0 ? offboardingText.interview.noneDeclared : respuestas.categorias.map((c) => cuestionario.categorias[c]?.nombre ?? c).join(', ')}
          </p>
          <ul className="flex flex-col gap-2">
            {Object.entries(cuestionario.abiertas).map(([clave, pregunta]) => (
              <li key={clave} className="rounded-lg border border-border p-3">
                <p className="text-[13px] font-semibold text-muted">{pregunta}</p>
                <p className="whitespace-pre-wrap text-[15px]">{respuestas.abiertas[clave] ?? offboardingText.interview.notAnswered}</p>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  )
}

type Confirmando = 'presentar' | 'declinar' | null

function Formulario({ entrevista, onActualizar }: { entrevista: MiEntrevista; onActualizar: () => void }) {
  const navigate = useNavigate()
  const { cuestionario } = entrevista
  const [valoraciones, setValoraciones] = useState<Record<string, number>>(entrevista.respuestas.valoraciones)
  const [categorias, setCategorias] = useState<string[]>(entrevista.respuestas.categorias)
  const [abiertas, setAbiertas] = useState<Record<string, string>>(entrevista.respuestas.abiertas)
  const [problema, setProblema] = useState<ProblemaDeSalida | null>(null)
  const [aviso, setAviso] = useState<string | null>(null)
  const [confirmando, setConfirmando] = useState<Confirmando>(null)
  const [errores, setErrores] = useState<Record<string, string>>({})
  const claveDe = useClaveDeOperacion()
  const guardar = useGuardarMiEntrevista()
  const resolver = useResolverMiEntrevista()

  const respuestas = (): RespuestasDeEntrevista => ({
    valoraciones,
    categorias,
    abiertas: Object.fromEntries(Object.entries(abiertas).filter(([, valor]) => valor.trim() !== '')),
  })

  const validar = (): boolean => {
    const nuevos: Record<string, string> = {}
    for (const [clave, texto] of Object.entries(abiertas)) {
      if (texto.length > TEXTO_ABIERTO_MAXIMO) nuevos[clave] = offboardingText.validation.openTooLong
    }
    setErrores(nuevos)
    return Object.keys(nuevos).length === 0
  }

  const alFallar = (error: unknown) => {
    setProblema(problemaDeSalida(error))
    setAviso(null)
  }

  // Se usan promesas (no los callbacks de `mutate`): el formulario puede desmontarse cuando la consulta se actualiza y
  // el flujo (guardar → presentar → volver) debe terminar igual.
  const guardarAvance = async () => {
    setProblema(null)
    if (!validar()) return
    const cuerpo = respuestas()
    try {
      await guardar.mutateAsync({ version: entrevista.version, respuestas: cuerpo, clave: claveDe(JSON.stringify([entrevista.version, cuerpo])) })
      setAviso(text.saved)
    } catch (error) {
      alFallar(error)
    }
  }

  /** Presentar guarda primero lo escrito (la versión cambia) y luego confirma con la versión nueva. */
  const guardarYPresentar = async () => {
    setProblema(null)
    const cuerpo = respuestas()
    try {
      const guardada = await guardar.mutateAsync({ version: entrevista.version, respuestas: cuerpo, clave: claveDe(JSON.stringify([entrevista.version, cuerpo])) })
      await resolver.mutateAsync({ accion: 'confirmacion', version: guardada.version, clave: claveDe(JSON.stringify(['confirmacion', guardada.version])) })
      navigate('/mi-desvinculacion', { state: { aviso: text.presented } })
    } catch (error) {
      alFallar(error)
    }
  }

  const declinar = async () => {
    setProblema(null)
    try {
      await resolver.mutateAsync({ accion: 'declinacion', version: entrevista.version, clave: claveDe(JSON.stringify(['declinacion', entrevista.version])) })
      navigate('/mi-desvinculacion', { state: { aviso: text.declined } })
    } catch (error) {
      alFallar(error)
    }
  }

  const alternarCategoria = (id: string) => setCategorias((actuales) => (actuales.includes(id) ? actuales.filter((c) => c !== id) : [...actuales, id]))
  const cerrarConfirmacion = () => {
    setConfirmando(null)
    setProblema(null)
  }
  const ocupado = guardar.isPending || resolver.isPending

  return (
    <form
      noValidate
      onSubmit={(evento) => {
        evento.preventDefault()
        void guardarAvance()
      }}
      className="flex flex-col gap-5"
    >
      {problema && !problema.conflicto && confirmando === null && <Alert>{problema.mensaje}</Alert>}
      {problema?.conflicto && (
        <Alert>
          <p className="font-semibold">{offboardingText.errors.conflictTitle}</p>
          <p>{offboardingText.errors.conflict}</p>
          <Button
            variant="secondary"
            onClick={() => {
              setProblema(null)
              onActualizar()
            }}
          >
            {offboardingText.refresh}
          </Button>
        </Alert>
      )}
      {aviso && <Alert tone="neutral">{aviso}</Alert>}

      <section className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5">
        <h2 className="text-lg font-bold">{text.ratingsTitle}</h2>
        <p className="text-[13px] text-muted">{text.ratingsHint}</p>
        {Object.entries(cuestionario.dimensiones).map(([clave, dimension]) => (
          <fieldset key={clave} className="flex flex-col gap-2">
            <legend className="text-sm font-semibold">{dimension.pregunta}</legend>
            <div className="flex flex-wrap gap-2">
              {Object.entries(cuestionario.escala).map(([valor, etiqueta]) => (
                <label key={valor} className="flex min-h-11 items-center gap-2 rounded-lg border border-border px-3 text-sm">
                  <input type="radio" name={`valoracion-${clave}`} className="size-4" checked={valoraciones[clave] === Number(valor)} onChange={() => setValoraciones((v) => ({ ...v, [clave]: Number(valor) }))} />
                  <span>{valor} · {etiqueta}</span>
                </label>
              ))}
              <label className="flex min-h-11 items-center gap-2 rounded-lg border border-border px-3 text-sm text-muted">
                <input
                  type="radio"
                  name={`valoracion-${clave}`}
                  className="size-4"
                  checked={valoraciones[clave] === undefined}
                  onChange={() => setValoraciones((v) => Object.fromEntries(Object.entries(v).filter(([otra]) => otra !== clave)))}
                />
                <span>{text.clearRating}</span>
              </label>
            </div>
          </fieldset>
        ))}
      </section>

      <fieldset className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        <legend className="px-1 text-lg font-bold">{text.reasonsTitle}</legend>
        <p className="text-[13px] text-muted">{text.reasonsHint}</p>
        {Object.entries(cuestionario.categorias).map(([clave, categoria]) => (
          <label key={clave} className="flex min-h-11 items-start gap-2 rounded-lg border border-border px-3 py-2 text-sm">
            <input type="checkbox" className="mt-0.5 size-4" checked={categorias.includes(clave)} onChange={() => alternarCategoria(clave)} />
            <span>
              <span className="font-semibold">{categoria.nombre}</span>
              <span className="block text-[13px] text-muted">{categoria.descripcion}</span>
            </span>
          </label>
        ))}
      </fieldset>

      <section className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5">
        <h2 className="text-lg font-bold">{text.openTitle}</h2>
        <p className="text-[13px] text-muted">{text.openHint}</p>
        {Object.entries(cuestionario.abiertas).map(([clave, pregunta]) => (
          <TextareaField key={clave} id={`abierta-${clave}`} label={pregunta} value={abiertas[clave] ?? ''} onChange={(e) => setAbiertas((a) => ({ ...a, [clave]: e.target.value }))} maxLength={TEXTO_ABIERTO_MAXIMO} currentLength={(abiertas[clave] ?? '').length} error={errores[clave]} />
        ))}
      </section>

      <div className="flex flex-wrap justify-end gap-2">
        <Button variant="secondary" onClick={() => setConfirmando('declinar')} disabled={ocupado}>
          {text.decline}
        </Button>
        <Button type="submit" variant="secondary" loading={guardar.isPending} disabled={resolver.isPending}>
          {text.save}
        </Button>
        <Button onClick={() => (validar() ? setConfirmando('presentar') : undefined)} disabled={ocupado}>
          {text.confirm}
        </Button>
      </div>

      <Modal
        open={confirmando !== null}
        title={confirmando === 'declinar' ? text.declineTitle : text.confirmTitle}
        onClose={cerrarConfirmacion}
        busy={resolver.isPending || guardar.isPending}
        description={confirmando === 'declinar' ? text.declineDescription : text.confirmDescription}
        footer={
          <>
            <Button variant="secondary" onClick={cerrarConfirmacion} disabled={resolver.isPending}>
              {offboardingText.cancel}
            </Button>
            <Button variant={confirmando === 'declinar' ? 'danger' : 'primary'} loading={resolver.isPending} onClick={() => void (confirmando === 'declinar' ? declinar() : guardarYPresentar())}>
              {confirmando === 'declinar' ? text.declineAction : text.confirmAction}
            </Button>
          </>
        }
      >
        <div className="flex flex-col gap-4">
          {problema && !problema.conflicto && <Alert>{problema.mensaje}</Alert>}
        </div>
      </Modal>
    </form>
  )
}

/** `/mi-desvinculacion/entrevista`: el Colaborador responde la entrevista de salida (voluntaria, preguntas opcionales). */
export function EntrevistaDeSalidaPage() {
  const consulta = useMiEntrevista()
  // Al actualizar tras un conflicto se vuelve a montar el formulario con lo que el servidor tiene ahora.
  const [generacion, setGeneracion] = useState(0)

  let contenido
  if (consulta.isPending) {
    contenido = (
      <div role="status" aria-label={text.loading} className="flex flex-col gap-3">
        <Skeleton className="h-24" />
        <Skeleton className="h-40" />
      </div>
    )
  } else if (consulta.isError) {
    const problema = problemaDeSalida(consulta.error)
    contenido = problema.noEncontrado ? (
      <div className="rounded-xl border border-border bg-surface">
        <EmptyState icon={<IconClock size={24} />} title={offboardingText.mine.emptyTitle} description={offboardingText.mine.emptyDescription} />
      </div>
    ) : (
      <div className="rounded-xl border border-border bg-surface">
        <ErrorState title={text.errorTitle} description={text.errorDescription} retryLabel={offboardingText.retry} onRetry={() => void consulta.refetch()} />
      </div>
    )
  } else if (consulta.data.cerrada) {
    contenido = <Cerrada entrevista={consulta.data} />
  } else {
    contenido = <Formulario key={generacion} entrevista={consulta.data} onActualizar={() => void consulta.refetch().then(() => setGeneracion((g) => g + 1))} />
  }

  return (
    <div className="flex flex-col gap-5">
      <Breadcrumb label="Ruta de navegación" items={[{ label: offboardingText.mine.title, to: '/mi-desvinculacion' }, { label: text.breadcrumb }]} />
      <header>
        <h1 className="text-2xl font-bold">{text.title}</h1>
        <p className="text-muted">{text.subtitle}</p>
      </header>
      {contenido}
    </div>
  )
}
