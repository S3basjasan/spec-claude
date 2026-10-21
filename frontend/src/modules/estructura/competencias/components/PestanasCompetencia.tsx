import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconPencil } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { Tabs } from '../../../../shared/ui/Tabs'
import type { CompetenciaDetalle, Escala } from '../api'
import { etiquetasDeEscala } from '../escalas'
import { useAplicarEscala } from '../hooks/useAplicarEscala'
import { useCompetencia } from '../hooks/useCompetencias'
import { useEscalas } from '../hooks/useEscalas'
import { competenciasText } from '../text'
import type { SeccionEditable } from './FormularioSeccion'
import { VistaPreviaEscala } from './VistaPreviaEscala'

const text = competenciasText.detail

type Pestana = SeccionEditable

interface PestanasCompetenciaProps {
  competenciaId: number
  nombre: string
  esAdmin: boolean
  onEditar: (seccion: SeccionEditable) => void
  /** Mensaje del aviso emergente cuando se aplica una escala. */
  onAviso: (mensaje: string) => void
}

function Etiqueta({ children }: { children: string }) {
  return <span className="inline-block self-start rounded-full bg-soft px-2.5 py-0.5 text-[13px] font-semibold text-accent">{children}</span>
}

function BotonEditar({ etiqueta, onClick }: { etiqueta: string; onClick: () => void }) {
  return (
    <button
      type="button"
      aria-label={etiqueta}
      title={etiqueta}
      onClick={onClick}
      className="inline-flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-text hover:bg-soft"
    >
      <IconPencil size={18} />
    </button>
  )
}

function Encabezado({ titulo, accion }: { titulo: string; accion?: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-2">
      <h3 className="text-[15px] font-semibold">{titulo}</h3>
      {accion}
    </div>
  )
}

function Contenido({ competencia, nombre, esAdmin, onEditar, onAviso, escalas }: {
  competencia: CompetenciaDetalle
  nombre: string
  esAdmin: boolean
  onEditar: (seccion: SeccionEditable) => void
  onAviso: (mensaje: string) => void
  escalas: Escala[] | undefined
}) {
  const [pestana, setPestana] = useState<Pestana>('competencia')
  const [previsualizada, setPrevisualizada] = useState<number | null>(null)
  const [errorEscala, setErrorEscala] = useState<string>()
  const aplicar = useAplicarEscala()

  const actualId = competencia.escala?.tipo_grado_id ?? null
  const hayVistaPrevia = previsualizada !== null && previsualizada !== actualId
  const escalaMostrada = escalas?.find((escala) => escala.id === (hayVistaPrevia ? previsualizada : actualId))
  const etiquetas = competencia.grados.map((grado, indice) =>
    hayVistaPrevia && escalaMostrada ? (etiquetasDeEscala(escalaMostrada, competencia.categorias)[indice] ?? grado.etiqueta) : grado.etiqueta,
  )
  const nombreEscala = escalaMostrada?.nombre ?? competencia.escala?.nombre ?? ''

  const aplicarEscala = () => {
    if (previsualizada === null) return
    setErrorEscala(undefined)
    aplicar.mutate(
      { id: competencia.id, tipoGradoId: previsualizada },
      {
        onSuccess: () => {
          setPrevisualizada(null)
          onAviso(competenciasText.notices.scaleApplied)
        },
        onError: () => setErrorEscala(competenciasText.scale.applyError),
      },
    )
  }

  const vistaPrevia =
    escalas && escalas.length > 0 ? (
      <VistaPreviaEscala
        escalas={escalas}
        actual={actualId}
        seleccion={previsualizada}
        onSeleccionar={(id) => {
          setErrorEscala(undefined)
          setPrevisualizada(id)
        }}
        onDescartar={() => {
          setErrorEscala(undefined)
          setPrevisualizada(null)
        }}
        esAdmin={esAdmin}
        onAplicar={aplicarEscala}
        aplicando={aplicar.isPending}
        error={errorEscala}
      />
    ) : null

  const faltan = competencia.faltantes.map((f) => text.missing[f]).join(' y ')

  return (
    <div className="flex flex-col gap-4 pt-4 text-[15px]">
      {esAdmin && !competencia.completa && <Alert tone="neutral">{faltan ? text.incompleteWith(faltan) : text.incomplete}</Alert>}
      <Tabs
        label={text.tabsLabel(nombre)}
        idBase={`competencia-${competencia.id}`}
        scrollable
        active={pestana}
        onChange={(id) => setPestana(id as Pestana)}
        tabs={[
          { id: 'competencia', label: text.tabs.competencia },
          { id: 'comportamientos', label: text.tabs.comportamientos },
          { id: 'preguntas', label: text.tabs.preguntas },
        ]}
      >
        {pestana === 'competencia' && (
          <div className="flex flex-col gap-3">
            <p className="leading-[1.45]">{competencia.descripcion}</p>
            <Encabezado
              titulo={text.gradesTitleScale(nombreEscala, hayVistaPrevia)}
              accion={esAdmin && <BotonEditar etiqueta={text.editCompetencia(nombre)} onClick={() => onEditar('competencia')} />}
            />
            {vistaPrevia}
            <div className="flex flex-col gap-3">
              {competencia.grados.map((grado, indice) => (
                <div key={grado.id} className="flex flex-col gap-0.5">
                  <Etiqueta>{etiquetas[indice]}</Etiqueta>
                  <span className="leading-[1.45]">{grado.descripcion}</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {pestana === 'comportamientos' && (
          <div className="flex flex-col gap-3.5">
            <Encabezado
              titulo={text.behaviorsTitlePreview(hayVistaPrevia)}
              accion={esAdmin && <BotonEditar etiqueta={text.editComportamientos(nombre)} onClick={() => onEditar('comportamientos')} />}
            />
            {vistaPrevia}
            <div className="flex flex-col gap-4">
              {competencia.grados.map((grado, indice) => (
                <div key={grado.id}>
                  <div className="mb-1.5">
                    <Etiqueta>{etiquetas[indice]}</Etiqueta>
                  </div>
                  <ul className="flex list-disc flex-col gap-1 pl-5">
                    {grado.comportamientos.map((comportamiento, numero) => (
                      <li key={numero}>{comportamiento}</li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          </div>
        )}

        {pestana === 'preguntas' && (
          <div className="flex flex-col gap-3">
            <Encabezado
              titulo={text.questionsTitle}
              accion={esAdmin && <BotonEditar etiqueta={text.editPreguntas(nombre)} onClick={() => onEditar('preguntas')} />}
            />
            <ol className="flex list-decimal flex-col gap-2 pl-5.5">
              {competencia.preguntas.map((pregunta, indice) => (
                <li key={indice}>{pregunta}</li>
              ))}
            </ol>
          </div>
        )}
      </Tabs>
    </div>
  )
}

/**
 * Parte desplegada de una tarjeta: las pestañas Competencia, Comportamientos y Preguntas. El detalle se carga al
 * abrir la tarjeta; cada tarjeta mantiene su propia pestaña y su propia vista previa de escala.
 */
export function PestanasCompetencia({ competenciaId, nombre, esAdmin, onEditar, onAviso }: PestanasCompetenciaProps) {
  const detalle = useCompetencia(competenciaId)
  const escalas = useEscalas()

  if (detalle.isPending) {
    return (
      <div role="status" aria-label={text.loading} className="flex flex-col gap-3 pt-4">
        <Skeleton className="h-10" />
        <Skeleton className="h-24" />
      </div>
    )
  }

  if (detalle.isError) {
    return (
      <ErrorState
        title={text.errorTitle}
        description={text.errorDescription}
        retryLabel={competenciasText.list.retry}
        onRetry={() => void detalle.refetch()}
      />
    )
  }

  return <Contenido competencia={detalle.data} nombre={nombre} esAdmin={esAdmin} onEditar={onEditar} onAviso={onAviso} escalas={escalas.data} />
}
