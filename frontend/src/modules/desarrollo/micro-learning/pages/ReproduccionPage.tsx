import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconDownload, IconPlayerPlay } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { Intento, Micro } from '../api'
import { AvisoConocimientos } from '../components/AvisoConocimientos'
import { EtiquetaTipo } from '../components/EtiquetaTipo'
import { QuizPlayer } from '../components/QuizPlayer'
import { ReproductorVideo } from '../components/ReproductorVideo'
import { VisorDocumento } from '../components/VisorDocumento'
import { useAvance, useCompletar, useDescargarCertificado, useIniciarIntento, useResponderPregunta } from '../hooks/useMutacionesMicro'
import { useMicro } from '../hooks/useMicros'
import { microText } from '../text'
import { formatearMinutos } from '../tiempo'

const t = microText.player

function Reproduccion({ micro }: { micro: Micro }) {
  const navigate = useNavigate()
  const completado = micro.progreso?.estado === 'Completado'
  const avance = useAvance(micro.id)
  const completar = useCompletar(micro.id)
  const certificado = useDescargarCertificado()
  const iniciar = useIniciarIntento(micro.id)
  const responder = useResponderPregunta()
  const [intento, setIntento] = useState<Intento | null>(null)
  const [errorQuiz, setErrorQuiz] = useState(false)
  const [errorCertificado, setErrorCertificado] = useState(false)
  const registrado = useRef(false)

  // Abrir un contenido que no es quiz crea su progreso En progreso (spec 3.4).
  const { mutate: registrarAvance } = avance
  useEffect(() => {
    if (registrado.current || micro.tipo === 'Quiz' || completado || micro.progreso != null) return
    registrado.current = true
    registrarAvance(micro.tipo === 'Video' ? 0 : null)
  }, [micro.tipo, micro.progreso, completado, registrarAvance])

  const empezarQuiz = (reiniciar: boolean) => {
    setErrorQuiz(false)
    return iniciar.mutateAsync(reiniciar).then(setIntento)
  }

  const competencia = micro.competencias[0]?.nombre ?? microText.sinCompetencia

  return (
    <div className="mx-auto flex max-w-225 flex-col gap-5">
      <div>
        <Link to="/micro-learning" className="text-sm font-semibold text-accent underline">
          {t.back}
        </Link>
        <div className="mt-3 flex flex-wrap items-center gap-2">
          <EtiquetaTipo tipo={micro.tipo} />
          <span className="text-[13px] font-semibold text-accent">{microText.aptoConocimientos}</span>
          {completado && <Badge>{t.completed}</Badge>}
        </div>
        <h1 className="mt-2 text-[28px] font-bold leading-tight tracking-tight">{micro.titulo}</h1>
        <p className="mt-1.5 text-base text-muted">{t.details(competencia, formatearMinutos(micro.duracion_minutos))}</p>
      </div>

      {micro.tipo === 'Video' && (
        <ReproductorVideo
          titulo={micro.titulo}
          duracionSegundos={micro.duracion_minutos * 60}
          posicionInicial={micro.progreso?.posicion_segundos ?? 0}
          modo="real"
          completado={completado}
          onPosicion={(segundos) => avance.mutate(segundos)}
        />
      )}
      {(micro.tipo === 'PDF' || micro.tipo === 'Articulo') && <VisorDocumento tipo={micro.tipo} titulo={micro.titulo} url={micro.url} duracionMinutos={micro.duracion_minutos} />}

      {micro.tipo === 'Quiz' && (
        <>
          {completado && micro.progreso?.ultimo_puntaje && (
            <p className="text-sm text-muted">{microText.colab.scoreLabel(micro.progreso.ultimo_puntaje.correctas, micro.progreso.ultimo_puntaje.total)}</p>
          )}
          {intento === null ? (
            <section className="flex flex-col items-start gap-3 rounded-xl border border-border bg-surface p-5">
              <p className="text-sm">{microText.quiz.intro}</p>
              {errorQuiz && (
                <p role="alert" className="text-[13px] text-danger">
                  {microText.quiz.startFailed}
                </p>
              )}
              <Button loading={iniciar.isPending} onClick={() => void empezarQuiz(false).catch(() => setErrorQuiz(true))}>
                {iniciar.isPending ? microText.quiz.starting : completado ? microText.quiz.retry : microText.quiz.start}
              </Button>
            </section>
          ) : (
            <QuizPlayer
              key={intento.id}
              preguntas={intento.preguntas}
              respondidas={intento.respondidas}
              onResponder={(preguntaId, opcionId) => responder.mutateAsync({ intentoId: intento.id, preguntaId, opcionId })}
              onReintentar={() => empezarQuiz(true)}
              onVolver={() => navigate('/micro-learning')}
            />
          )}
        </>
      )}

      {micro.tipo !== 'Quiz' && (
        <div className="flex flex-col items-start gap-2">
          {completado ? (
            <p role="status" className="text-sm font-semibold text-accent">
              {t.completedNotice}
            </p>
          ) : (
            <Button loading={completar.isPending} onClick={() => completar.mutate()}>
              {completar.isPending ? t.completing : t.complete}
            </Button>
          )}
          {completar.isSuccess && (
            <p role="status" className="text-sm text-muted">
              {t.progressSaved}
            </p>
          )}
          {completar.isError && <Alert>{t.completeFailed}</Alert>}
        </div>
      )}

      {completado && (
        <div className="flex flex-col items-start gap-2">
          <Button
            variant="secondary"
            loading={certificado.isPending}
            onClick={() => {
              setErrorCertificado(false)
              certificado.mutate({ id: micro.id, codigo: micro.codigo }, { onError: () => setErrorCertificado(true) })
            }}
          >
            <IconDownload size={16} />
            {t.downloadCertificate}
          </Button>
          {errorCertificado && <Alert>{microText.colab.certificateFailed}</Alert>}
        </div>
      )}

      <AvisoConocimientos detalle />
    </div>
  )
}

/** Reproducción de un micro-contenido (colaborador): Video, PDF, Artículo o Quiz, según su tipo. */
export function ReproduccionPage() {
  const { id } = useParams()
  const numero = Number(id)
  const valido = Number.isInteger(numero) && numero > 0
  const consulta = useMicro(valido ? numero : null, true)

  if (!valido) {
    return <EmptyState icon={<IconPlayerPlay size={24} />} title={t.notAvailable} description={t.notAvailableDescription} action={<Link to="/micro-learning" className="font-semibold text-accent underline">{t.back}</Link>} />
  }

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={microText.loadingLabel} className="mx-auto flex max-w-225 flex-col gap-4">
        <Skeleton className="h-8" />
        <Skeleton className="h-64" />
      </div>
    )
  }

  if (consulta.isError) {
    const sinDisponibilidad = toApiError(consulta.error).status === 404
    return sinDisponibilidad ? (
      <EmptyState
        icon={<IconPlayerPlay size={24} />}
        title={t.notAvailable}
        description={t.notAvailableDescription}
        action={<Link to="/micro-learning" className="font-semibold text-accent underline">{t.back}</Link>}
      />
    ) : (
      <ErrorState title={microText.errorTitle} description={microText.errorDescription} retryLabel={microText.retry} onRetry={() => void consulta.refetch()} />
    )
  }

  return <Reproduccion key={consulta.data.id} micro={consulta.data} />
}
