import { useId, useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconX } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { NIVELES, type Recurso, type TipoRecurso } from '../api'
import { formatearFecha } from '../fecha'
import { useAreas, useDetalleFormativo } from '../hooks/useCatalogo'
import { useGuardarConfiguracion } from '../hooks/useGuardarConfiguracion'
import { catalogoText } from '../text'
import { AreaFormativaSelect } from './AreaFormativaSelect'
import { HistorialLista } from './HistorialLista'
import { RecursoItem } from './RecursoItem'
import { SelectorDeRecursos } from './SelectorDeRecursos'
import { useDialogoAccesible } from './useDialogoAccesible'

interface DetalleCompetenciaPanelProps {
  competenciaId: number
  onCerrar: () => void
}

/** Cambios aún sin guardar: se aplican todos juntos con Guardar. */
interface Edicion {
  areaId: number | null
  cursos: Recurso[]
  micros: Recurso[]
}

type Problema = 'conflicto' | 'noExiste' | 'general' | 'validacion' | null

const text = catalogoText.panel

const mismosIds = (a: Recurso[], b: Recurso[]) => a.length === b.length && a.every((recurso) => b.some((otro) => otro.id === recurso.id))

/**
 * Panel lateral (slide-over) de una competencia: sección de solo lectura con los datos de Estructura Empresarial y
 * sección editable (área, cursos y micro-contenidos) que se confirma con Guardar.
 */
export function DetalleCompetenciaPanel({ competenciaId, onCerrar }: DetalleCompetenciaPanelProps) {
  const tituloId = useId()
  const consulta = useDetalleFormativo(competenciaId, true)
  const areas = useAreas()
  const detalle = consulta.data
  const guardar = useGuardarConfiguracion(competenciaId, detalle?.version ?? 0)

  const [edicion, setEdicion] = useState<Edicion | null>(null)
  const [selector, setSelector] = useState<TipoRecurso | null>(null)
  const [quitando, setQuitando] = useState<{ recurso: Recurso; tipo: TipoRecurso } | null>(null)
  const [descartando, setDescartando] = useState(false)
  const [guardado, setGuardado] = useState(false)
  const [problema, setProblema] = useState<Problema>(null)
  const [errores, setErrores] = useState<Record<string, string[]>>({})

  const base: Edicion | null = detalle
    ? { areaId: detalle.area_formativa?.id ?? null, cursos: detalle.cursos, micros: detalle.microcontenidos }
    : null
  const actual = edicion ?? base
  const hayCambios =
    base !== null &&
    edicion !== null &&
    (edicion.areaId !== base.areaId || !mismosIds(edicion.cursos, base.cursos) || !mismosIds(edicion.micros, base.micros))

  const solicitarCierre = () => (hayCambios ? setDescartando(true) : onCerrar())
  const { ref, onKeyDown } = useDialogoAccesible(solicitarCierre, guardar.isPending)

  const editar = (parcial: Partial<Edicion>) => {
    if (actual === null) return
    setEdicion({ ...actual, ...parcial })
    setGuardado(false)
    setProblema(null)
    setErrores({})
  }

  const recargar = () => {
    setEdicion(null)
    setProblema(null)
    setErrores({})
    void consulta.refetch()
  }

  const onGuardar = () => {
    if (actual === null) return
    guardar.mutate(
      {
        area_formativa_id: actual.areaId,
        curso_ids: actual.cursos.map((recurso) => recurso.id),
        microcontenido_ids: actual.micros.map((recurso) => recurso.id),
      },
      {
        onSuccess: () => {
          setEdicion(null)
          setGuardado(true)
          setProblema(null)
          setErrores({})
        },
        onError: (error) => {
          const api = toApiError(error)
          setGuardado(false)
          if (api.status === 409) return setProblema('conflicto')
          if (api.status === 404) return setProblema('noExiste')
          if (api.status === 422) {
            setErrores(api.fieldErrors)
            return setProblema('validacion')
          }
          setProblema('general')
        },
      },
    )
  }

  const confirmarQuitar = () => {
    if (quitando === null || actual === null) return
    if (quitando.tipo === 'curso') editar({ cursos: actual.cursos.filter((recurso) => recurso.id !== quitando.recurso.id) })
    else editar({ micros: actual.micros.filter((recurso) => recurso.id !== quitando.recurso.id) })
    setQuitando(null)
  }

  const agregar = (tipo: TipoRecurso, recursos: Recurso[]) => {
    if (actual === null) return
    if (tipo === 'curso') editar({ cursos: [...actual.cursos, ...recursos] })
    else editar({ micros: [...actual.micros, ...recursos] })
    setSelector(null)
  }

  const erroresGenerales = Object.entries(errores)
    .filter(([campo]) => campo !== 'area_formativa_id')
    .flatMap(([, mensajes]) => mensajes)

  const inactiva = detalle?.competencia.estado === 'Inactivo'

  const lista = (tipo: TipoRecurso, recursos: Recurso[]) => {
    const disponibles = recursos.filter((recurso) => recurso.estado === 'Disponible').length
    const archivados = recursos.length - disponibles
    const titulo = tipo === 'curso' ? text.coursesTitle : text.microTitle
    const nombreLista = `${titulo} (${disponibles}${archivados > 0 ? ` · ${catalogoText.archivados(archivados)}` : ''})`

    return (
      <section aria-label={titulo} className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h4 className="text-base font-bold">{nombreLista}</h4>
          <Button variant="secondary" disabled={inactiva} onClick={() => setSelector(tipo)}>
            {tipo === 'curso' ? text.associateCourse : text.associateMicro}
          </Button>
        </div>
        {tipo === 'microcontenido' && <p className="text-[13px] text-muted">{catalogoText.notaMicrocontenidos}</p>}
        {recursos.length === 0 ? (
          <p className="text-sm text-muted">{tipo === 'curso' ? text.emptyCourses : text.emptyMicro}</p>
        ) : (
          <ul aria-label={titulo} className="m-0 flex list-none flex-col gap-2 p-0">
            {recursos.map((recurso) => (
              <RecursoItem key={recurso.id} recurso={recurso} onQuitar={() => setQuitando({ recurso, tipo })} />
            ))}
          </ul>
        )}
      </section>
    )
  }

  const contenido = () => {
    if (consulta.isPending || actual === null || detalle === undefined) {
      if (consulta.isError) {
        const noExiste = toApiError(consulta.error).status === 404
        return (
          <ErrorState
            title={noExiste ? text.notFoundTitle : text.errorTitle}
            description={noExiste ? text.notFoundDescription : text.errorDescription}
            retryLabel={catalogoText.admin.retry}
            onRetry={() => void consulta.refetch()}
          />
        )
      }
      return (
        <div role="status" aria-label={text.loadingLabel} className="flex flex-col gap-3">
          {[0, 1, 2].map((fila) => (
            <Skeleton key={fila} className="h-12" />
          ))}
        </div>
      )
    }

    if (selector !== null) {
      return (
        <SelectorDeRecursos
          tipo={selector}
          competenciaId={competenciaId}
          excluidos={[...actual.cursos, ...actual.micros].map((recurso) => recurso.id)}
          onAsociar={(recursos) => agregar(selector, recursos)}
          onVolver={() => setSelector(null)}
        />
      )
    }

    const competencia = detalle.competencia
    return (
      <div className="flex flex-col gap-5">
        {inactiva && <Alert tone="neutral">{text.inactiveNotice}</Alert>}

        {problema === 'conflicto' && (
          <Alert>
            <p className="font-semibold">{text.errors.conflictTitle}</p>
            <p>{text.errors.conflictDescription}</p>
            <Button variant="secondary" onClick={recargar} className="mt-2">
              {text.reload}
            </Button>
          </Alert>
        )}
        {problema === 'noExiste' && <Alert>{text.errors.notFound}</Alert>}
        {problema === 'general' && <Alert>{text.errors.generic}</Alert>}
        {problema === 'validacion' && (
          <Alert>
            <p>{text.errors.validation}</p>
            {erroresGenerales.length > 0 && (
              <ul className="m-0 list-disc pl-5">
                {erroresGenerales.map((mensaje) => (
                  <li key={mensaje}>{mensaje}</li>
                ))}
              </ul>
            )}
          </Alert>
        )}
        {guardado && (
          <p role="status" className="rounded-lg bg-soft px-3.5 py-3 text-sm font-semibold text-accent">
            {text.saved}
          </p>
        )}

        <section aria-label={text.readOnlyTitle} className="flex flex-col gap-3 rounded-xl border border-border bg-bg p-4">
          <p className="text-[13px] font-semibold text-muted">{text.readOnlyTitle}</p>
          <div className="flex flex-wrap items-center gap-2">
            <Badge>{catalogoText.tipoCompetencia[competencia.tipo]}</Badge>
            <Badge variant={inactiva ? 'neutral' : 'default'}>{catalogoText.estadoCompetencia[competencia.estado]}</Badge>
          </div>
          <div>
            <h3 className="text-sm font-semibold">{text.description}</h3>
            <p className="text-sm">{competencia.descripcion}</p>
          </div>
          <div>
            <h3 className="text-sm font-semibold">{text.gradesTitle}</h3>
            <dl className="m-0 mt-1 flex flex-col gap-1.5">
              {NIVELES.map((nivel) => (
                <div key={nivel} className="flex gap-3 text-sm">
                  <dt className="w-6 shrink-0 font-bold text-accent">{nivel}</dt>
                  <dd className="m-0">{competencia.grados[nivel]}</dd>
                </div>
              ))}
            </dl>
          </div>
        </section>

        <section aria-label={text.editableTitle} className="flex flex-col gap-5">
          <AreaFormativaSelect
            id="area-formativa"
            areas={areas.data ?? []}
            value={actual.areaId}
            onChange={(areaId) => editar({ areaId })}
            error={errores.area_formativa_id?.[0]}
          />
          {lista('curso', actual.cursos)}
          {lista('microcontenido', actual.micros)}
          <p className="text-[13px] text-muted">
            {detalle.ultima_configuracion
              ? text.lastConfiguration(detalle.ultima_configuracion.usuario, formatearFecha(detalle.ultima_configuracion.fecha))
              : text.neverConfigured}
          </p>
        </section>

        <section aria-label={text.historyTitle} className="flex flex-col gap-3">
          <h3 className="text-base font-bold">{text.historyTitle}</h3>
          <HistorialLista competenciaId={competenciaId} />
        </section>
      </div>
    )
  }

  return (
    <>
      <div className="fixed inset-0 z-40 flex justify-end bg-black/40">
        <div
          ref={ref}
          role="dialog"
          aria-modal="true"
          aria-labelledby={tituloId}
          tabIndex={-1}
          onKeyDown={onKeyDown}
          className="flex h-full w-full max-w-140 flex-col border-l border-border bg-surface shadow-[0_12px_32px_rgba(0,0,0,0.14)]"
        >
          <header className="flex items-start justify-between gap-3 border-b border-border px-6 py-4">
            <div className="min-w-0">
              {detalle && (
                <p className="text-[13px] text-muted">
                  {detalle.competencia.codigo} · {detalle.area_formativa?.nombre ?? catalogoText.sinArea}
                </p>
              )}
              <h2 id={tituloId} className="text-lg font-bold">
                {detalle ? detalle.competencia.nombre : text.loadingLabel}
              </h2>
            </div>
            <button
              type="button"
              aria-label={text.closeLabel}
              onClick={solicitarCierre}
              disabled={guardar.isPending}
              className="inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-muted hover:bg-soft disabled:opacity-60"
            >
              <IconX size={18} />
            </button>
          </header>

          <div className="flex-1 overflow-y-auto px-6 py-5">{contenido()}</div>

          {detalle && selector === null && (
            <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-6 py-4">
              <span className="text-[13px] text-muted">{hayCambios ? text.unsavedHint : ''}</span>
              <div className="flex gap-3">
                <Button variant="secondary" onClick={solicitarCierre} disabled={guardar.isPending}>
                  {text.cancel}
                </Button>
                <Button onClick={onGuardar} loading={guardar.isPending} disabled={!hayCambios}>
                  {guardar.isPending ? text.saving : text.save}
                </Button>
              </div>
            </footer>
          )}
        </div>
      </div>

      <ConfirmDialog
        open={quitando !== null}
        title={quitando ? text.removeDialog.title(quitando.recurso.nombre) : ''}
        description={text.removeDialog.description}
        confirmLabel={text.removeDialog.confirm}
        cancelLabel={text.removeDialog.cancel}
        onConfirm={confirmarQuitar}
        onCancel={() => setQuitando(null)}
      />
      <ConfirmDialog
        open={descartando}
        title={text.discardDialog.title}
        description={text.discardDialog.description}
        confirmLabel={text.discardDialog.confirm}
        cancelLabel={text.discardDialog.cancel}
        onConfirm={() => {
          setDescartando(false)
          onCerrar()
        }}
        onCancel={() => setDescartando(false)}
      />
    </>
  )
}
