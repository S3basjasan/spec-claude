import { useState } from 'react'
import { useLocation, useParams } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { CondicionesAplicables, type Parametros } from '../../politicas'
import type { AccionDeActuacion, CuerpoDeAccion, DetalleActuacion } from '../api'
import { AvisoDeCruces } from '../components/AvisoDeCruces'
import { DialogoDeResolucion, type ModoDeResolucion, type ValoresDeResolucion } from '../components/DialogoDeResolucion'
import { EstadoDeActuacion } from '../components/EstadoDeActuacion'
import { EvidenciaDeEvaluacion } from '../components/EvidenciaDeEvaluacion'
import { ResumenDeComprobaciones } from '../components/ResumenDeComprobaciones'
import { problemaDeActuacion, type ProblemaDeActuacion } from '../errores'
import { useAccionDeActuacion } from '../hooks/useAccionesDeActuacion'
import { useActuacion } from '../hooks/useActuaciones'
import { useClaveDeOperacion } from '../hooks/useClaveDeOperacion'
import { movimientosText } from '../text'

const text = movimientosText.detail

function momento(iso: string | null): string {
  if (!iso) return movimientosText.none
  const valor = new Date(iso)
  return Number.isNaN(valor.getTime()) ? iso : valor.toLocaleString('es-EC', { dateStyle: 'medium', timeStyle: 'short' })
}

function Dato({ etiqueta, children }: { etiqueta: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5">
      <dt className="text-[13px] font-semibold text-muted">{etiqueta}</dt>
      <dd className="text-[15px]">{children}</dd>
    </div>
  )
}

const ACCION_DEL_MODO: Record<ModoDeResolucion, AccionDeActuacion> = {
  aprobar: 'aprobacion',
  rechazar: 'rechazo',
  retirar: 'retiro',
  'revisar-ausencia': 'revision-de-ausencia',
  aplicar: 'aplicacion',
}

const EXITO_DEL_MODO: Record<ModoDeResolucion, string> = {
  aprobar: movimientosText.resolution.approved,
  rechazar: movimientosText.resolution.rejected,
  retirar: movimientosText.resolution.withdrawn,
  'revisar-ausencia': movimientosText.resolution.reviewedAbsence,
  aplicar: movimientosText.resolution.applied,
}

function cuerpoDe(modo: ModoDeResolucion, version: number, v: ValoresDeResolucion): CuerpoDeAccion {
  if (modo === 'aprobar') return { version, justificacion: v.justificacion, reconoce_cruce: v.reconoceCruce }
  if (modo === 'rechazar') return { version, justificacion: v.justificacion }
  if (modo === 'revisar-ausencia') return { version, resultado: v.resultado, observacion: v.observacion }
  return { version }
}

function Cuerpo({ detalle }: { detalle: DetalleActuacion }) {
  const claveDe = useClaveDeOperacion()
  const accion = useAccionDeActuacion(detalle.id)
  const aviso = (useLocation().state as { aviso?: string } | null)?.aviso
  const [modo, setModo] = useState<ModoDeResolucion | null>(null)
  const [problema, setProblema] = useState<ProblemaDeActuacion | null>(null)
  const [exito, setExito] = useState<string | undefined>(aviso)
  const [intento, setIntento] = useState(0)

  const resoluble = detalle.estado === 'Presentada' || detalle.estado === 'EnRevision'
  const esMovimiento = detalle.categoria === 'Movimiento'
  const incidencia = detalle.movimiento?.aplicacion.incidencia ?? null
  const acciones = detalle.acciones
  const hayAcciones = Object.values(acciones).some(Boolean)

  const ejecutar = (nombre: AccionDeActuacion, cuerpo: CuerpoDeAccion, mensajeDeExito: string, alTerminar?: () => void) => {
    setProblema(null)
    setExito(undefined)
    accion.mutate(
      { accion: nombre, cuerpo, clave: claveDe(JSON.stringify([nombre, cuerpo])) },
      {
        onSuccess: () => {
          setExito(mensajeDeExito)
          alTerminar?.()
        },
        onError: (error) => setProblema(problemaDeActuacion(error)),
      },
    )
  }

  const confirmar = (valores: ValoresDeResolucion) => {
    if (modo === null) return
    ejecutar(ACCION_DEL_MODO[modo], cuerpoDe(modo, detalle.version, valores), EXITO_DEL_MODO[modo], () => {
      setModo(null)
      setIntento((n) => n + 1)
    })
  }

  const parametros = Object.fromEntries(Object.entries(detalle.politica.parametros).filter(([, valor]) => valor !== undefined)) as Parametros

  return (
    <div className="flex flex-col gap-5">
      <Breadcrumb label="Ruta de navegación" items={[{ label: movimientosText.list.title, to: '/movimientos-personal' }, { label: text.breadcrumb }]} />

      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{movimientosText.tipos[detalle.tipo]}</h1>
          <p className="text-muted">{detalle.colaborador.nombre} {detalle.colaborador.codigo ? `(${detalle.colaborador.codigo})` : ''}</p>
        </div>
        <EstadoDeActuacion estado={detalle.estado} />
      </header>

      {exito && <Alert tone="neutral">{exito}</Alert>}
      {problema?.conflicto && (
        <Alert>
          <p className="font-semibold">{movimientosText.errors.conflictTitle}</p>
          <p>{problema.mensaje}</p>
          <Button variant="secondary" className="mt-2" onClick={() => setProblema(null)}>
            {movimientosText.refresh}
          </Button>
        </Alert>
      )}
      {problema && !problema.conflicto && modo === null && <Alert>{problema.mensaje}</Alert>}

      <section aria-label={text.person} className="rounded-xl border border-border bg-surface p-5">
        <dl className="grid gap-4 sm:grid-cols-2">
          <Dato etiqueta={text.presented}>{momento(detalle.presentada.en)}</Dato>
          <Dato etiqueta={text.presentedBy}>
            {detalle.presentada.por.nombre ?? movimientosText.none}
            {detalle.presentada.en_nombre_de_colaborador && <span className="block text-[13px] text-muted">{text.onBehalf}</span>}
          </Dato>
          {detalle.periodo && (
            <Dato etiqueta={text.period}>
              {detalle.periodo.inicio === detalle.periodo.fin ? detalle.periodo.inicio : `${detalle.periodo.inicio} → ${detalle.periodo.fin}`}
              {detalle.periodo.ocurrida && <span className="block text-[13px] text-muted">{text.occurred}</span>}
            </Dato>
          )}
          <Dato etiqueta={text.reason}>{detalle.motivo ?? movimientosText.none}</Dato>
          {detalle.revision && <Dato etiqueta={movimientosText.resolution.observation}>{detalle.revision.observacion}</Dato>}
        </dl>
      </section>

      {detalle.movimiento && (
        <section aria-labelledby="bloque-movimiento" className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5">
          <h2 id="bloque-movimiento" className="text-lg font-bold">{text.application}</h2>
          <dl className="grid gap-4 sm:grid-cols-3">
            <Dato etiqueta={text.currentPosition}>
              {detalle.movimiento.posicion_actual ? `${detalle.movimiento.posicion_actual.puesto} · ${detalle.movimiento.posicion_actual.unidad ?? movimientosText.none}` : movimientosText.none}
            </Dato>
            <Dato etiqueta={text.destination}>
              {detalle.movimiento.posicion_destino.puesto ?? movimientosText.none} · {detalle.movimiento.posicion_destino.unidad ?? movimientosText.none}
            </Dato>
            <Dato etiqueta={text.effectiveDate}>{detalle.movimiento.fecha_efectiva}</Dato>
          </dl>

          <div className="flex flex-col gap-2">
            <h3 className="text-base font-bold">{text.evaluation}</h3>
            <EvidenciaDeEvaluacion evaluacion={detalle.movimiento.evaluacion} />
          </div>

          <div className="flex flex-col gap-2">
            <p className="font-semibold">{text.applicationStates[detalle.movimiento.aplicacion.estado]}</p>
            {detalle.movimiento.aplicacion.aplicada_en ? (
              <p className="text-sm">{text.appliedAt(momento(detalle.movimiento.aplicacion.aplicada_en), detalle.movimiento.aplicacion.por_sistema)}</p>
            ) : (
              <p className="text-sm text-muted">{text.notApplied}</p>
            )}
            {incidencia && (
              <Alert>
                <p className="font-semibold">{text.incidenceTitle}</p>
                <p>{text.incidences[incidencia.codigo] ?? incidencia.detalle}</p>
                <p>{text.incidenceNotice}</p>
              </Alert>
            )}
          </div>
        </section>
      )}

      <section aria-labelledby="bloque-comprobaciones" className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        <h2 id="bloque-comprobaciones" className="text-lg font-bold">{text.checks}</h2>
        <ResumenDeComprobaciones comprobaciones={detalle.comprobaciones} resoluble={resoluble && detalle.tipo !== 'Ausencia'} />
        {resoluble && !detalle.puede_aprobar && detalle.motivo_no_aprobable && (
          <Alert tone="neutral">{text.cannotApprove[detalle.motivo_no_aprobable] ?? text.cannotApprove.ESTADO_NO_RESOLUBLE}</Alert>
        )}
        <AvisoDeCruces cruces={detalle.cruces} />
        {detalle.cruce_reconocido && <p className="text-sm text-muted">{text.crossingAcknowledged}</p>}
      </section>

      <section aria-labelledby="bloque-respaldos" className="flex flex-col gap-2 rounded-xl border border-border bg-surface p-5">
        <h2 id="bloque-respaldos" className="text-lg font-bold">{text.supports}</h2>
        {detalle.documentos.length === 0 ? (
          <p className="text-sm text-muted">{text.supportsNone}</p>
        ) : (
          <ul className="flex flex-col gap-1 text-sm">
            {detalle.documentos.map((documento) => (
              <li key={documento.id}>
                {text.supportRow(documento.id)} — {documento.disponible ? text.supportAvailable : text.supportUnavailable}
              </li>
            ))}
          </ul>
        )}
      </section>

      <CondicionesAplicables
        tipo={detalle.politica.tipo.toLowerCase() as 'permisos'}
        condicionesConservadas={{ numero: detalle.politica.numero, parametros, reglas_fijas: detalle.politica.reglas_fijas }}
      />

      <section aria-labelledby="bloque-historial" className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        <h2 id="bloque-historial" className="text-lg font-bold">{text.history}</h2>
        <ol className="flex flex-col gap-3">
          {detalle.historial.map((evento, indice) => (
            <li key={`${evento.evento}-${indice}`} className="flex flex-col gap-0.5 border-l-2 border-border pl-3">
              <span className="font-semibold">{text.historyEvents[evento.evento] ?? evento.evento}</span>
              <span className="text-[13px] text-muted">
                {evento.actor.tipo === 'sistema' ? text.system : (evento.actor.nombre ?? movimientosText.none)} · {momento(evento.en)}
              </span>
              {evento.justificacion && <span className="text-sm">{evento.justificacion}</span>}
            </li>
          ))}
        </ol>
      </section>

      <section aria-labelledby="bloque-acciones" className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        <h2 id="bloque-acciones" className="text-lg font-bold">{text.actions}</h2>
        {!hayAcciones && <p className="text-sm text-muted">{text.noActions}</p>}
        {resoluble && esMovimiento && <p className="text-sm text-muted">{text.approveNotice}</p>}
        <div className="flex flex-wrap gap-2">
          {acciones.revisar && (
            <Button variant="secondary" loading={accion.isPending} onClick={() => ejecutar('revision', { version: detalle.version }, movimientosText.resolution.reviewing)}>
              {text.startReview}
            </Button>
          )}
          {acciones.aprobar && <Button onClick={() => { setProblema(null); setModo('aprobar') }}>{text.approve}</Button>}
          {acciones.rechazar && <Button variant="danger" onClick={() => { setProblema(null); setModo('rechazar') }}>{text.reject}</Button>}
          {acciones.retirar && <Button variant="secondary" onClick={() => { setProblema(null); setModo('retirar') }}>{text.withdraw}</Button>}
          {acciones.revisar_ausencia && <Button onClick={() => { setProblema(null); setModo('revisar-ausencia') }}>{text.reviewAbsence}</Button>}
          {acciones.aplicar && <Button onClick={() => { setProblema(null); setModo('aplicar') }}>{incidencia ? text.retryApply : text.applyNow}</Button>}
        </div>
      </section>

      <DialogoDeResolucion
        key={intento}
        modo={modo ?? 'aprobar'}
        open={modo !== null}
        cruces={detalle.cruces}
        busy={accion.isPending}
        error={problema?.mensaje}
        onConfirm={confirmar}
        onCancel={() => {
          setModo(null)
          setProblema(null)
        }}
      />
    </div>
  )
}

/** `/movimientos-personal/:id`: detalle, comprobaciones, resolución y aplicación (solo Administrador). */
export function ActuacionDetallePage() {
  const { id } = useParams()
  const consulta = useActuacion(Number(id))

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={text.loading} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2, 3].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  }

  if (consulta.isError) {
    const noExiste = problemaDeActuacion(consulta.error).noEncontrado
    return (
      <div className="rounded-xl border border-border bg-surface">
        {noExiste ? (
          <EmptyState icon={<IconFileText size={24} />} title={text.notFoundTitle} description={text.notFoundDescription} />
        ) : (
          <ErrorState title={text.errorTitle} description={text.errorDescription} retryLabel={movimientosText.retry} onRetry={() => void consulta.refetch()} />
        )}
      </div>
    )
  }

  return <Cuerpo detalle={consulta.data} />
}
