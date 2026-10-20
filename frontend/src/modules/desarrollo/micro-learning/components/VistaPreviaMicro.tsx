import { useState } from 'react'
import { Modal } from '../../../../shared/ui/Modal'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import type { Micro } from '../api'
import { useMicro } from '../hooks/useMicros'
import { evaluarLocal, preguntasCompletas } from '../previa'
import { microText } from '../text'
import { formatearMinutos } from '../tiempo'
import { AvisoConocimientos } from './AvisoConocimientos'
import { EtiquetaTipo } from './EtiquetaTipo'
import { QuizPlayer } from './QuizPlayer'
import { ReproductorVideo } from './ReproductorVideo'
import { VisorDocumento } from './VisorDocumento'

interface VistaPreviaMicroProps {
  /** Contenido guardado: se lee su detalle (con la opción correcta, que solo recibe el Administrador). */
  id?: number
  /** Contenido aún sin guardar (borrador del formulario). */
  micro?: Micro
  onCerrar: () => void
}

function Contenido({ micro }: { micro: Micro }) {
  const t = microText
  const [ronda, setRonda] = useState(0)
  const competencia = micro.competencias[0]?.nombre ?? t.sinCompetencia

  const preguntas = preguntasCompletas(micro)

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-1.5">
        <div className="flex flex-wrap items-center gap-2">
          <EtiquetaTipo tipo={micro.tipo} />
          <span className="text-[13px] font-semibold text-accent">{t.aptoConocimientos}</span>
        </div>
        <h3 className="text-xl font-bold leading-snug">{micro.titulo || t.preview.title}</h3>
        <p className="text-sm text-muted">{t.player.details(competencia, formatearMinutos(micro.duracion_minutos))}</p>
      </div>

      {micro.tipo === 'Video' && <ReproductorVideo titulo={micro.titulo} duracionSegundos={micro.duracion_minutos * 60} posicionInicial={0} modo="previa" url={micro.url} />}
      {(micro.tipo === 'PDF' || micro.tipo === 'Articulo') && (
        <VisorDocumento tipo={micro.tipo} titulo={micro.titulo} url={micro.url} duracionMinutos={micro.duracion_minutos} />
      )}
      {micro.tipo === 'Quiz' && (
        <QuizPlayer
          key={ronda}
          preguntas={preguntas}
          onResponder={(preguntaId, opcionId) => Promise.resolve(evaluarLocal(micro, preguntaId, opcionId))}
          onReintentar={() => {
            setRonda((r) => r + 1)
            return Promise.resolve()
          }}
        />
      )}
      <p className="text-[13px] text-muted">{t.preview.notice}</p>
    </div>
  )
}

/**
 * Vista previa tal como la verá el colaborador (spec 4.4). Es solo del cliente: reutiliza los componentes del
 * colaborador en modo `previa` y no hace ninguna petición de progreso ni de intentos.
 */
export function VistaPreviaMicro({ id, micro, onCerrar }: VistaPreviaMicroProps) {
  const detalle = useMicro(micro ? null : (id ?? null), true)
  const t = microText.preview

  return (
    <Modal open title={t.title} onClose={onCerrar} size="lg" closeLabel={t.close} nested>
      {micro ? (
        <Contenido micro={micro} />
      ) : detalle.isPending ? (
        <div role="status" aria-label={microText.loadingLabel} className="flex flex-col gap-3">
          <Skeleton className="h-6" />
          <Skeleton className="h-40" />
        </div>
      ) : detalle.isError ? (
        <ErrorState title={t.loadFailed} description={microText.errorDescription} retryLabel={microText.retry} onRetry={() => void detalle.refetch()} />
      ) : (
        <Contenido micro={detalle.data} />
      )}
    </Modal>
  )
}
