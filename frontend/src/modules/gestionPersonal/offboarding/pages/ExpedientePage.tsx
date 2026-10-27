import { useState, type ReactNode } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Badge } from '../../../../shared/ui/Badge'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { AccionDeSalida, CuerpoDeAccion, DetalleDeSalida, MotivoDeNoRealizada, TareaDeSalida } from '../api'
import { DialogoDeCancelacion, DialogoDeConfirmacion, DialogoDeNoRealizada, DialogoDeTarea } from '../components/DialogosDeExpediente'
import { EstadoDeLaEntrevista, EstadoDeLaSalida, EstadoDelCierre } from '../components/Estados'
import { RespuestasConsultadas } from '../components/RespuestasConsultadas'
import { problemaDeSalida, type ProblemaDeSalida } from '../errores'
import { momentoLegible } from '../format'
import { useClaveDeOperacion } from '../hooks/useClaveDeOperacion'
import { useAccionDeSalida, useCompletarTarea, useSalida } from '../hooks/useOffboarding'
import { offboardingText } from '../text'

const text = offboardingText.file

type Dialogo = { tipo: 'cancelar' | 'aplicar' | 'cerrar' | 'no-realizada' } | { tipo: 'tarea'; tarea: TareaDeSalida } | null

function Seccion({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section aria-label={titulo} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
      <h2 className="text-lg font-bold">{titulo}</h2>
      {children}
    </section>
  )
}

function PanelDeAplicacion({ salida, onAplicar }: { salida: DetalleDeSalida; onAplicar: () => void }) {
  const t = offboardingText.application
  const { aplicacion } = salida

  return (
    <Seccion titulo={text.sections.application}>
      {salida.estado === 'Programada' && <p className="text-sm">{t.scheduled(salida.fecha_efectiva)}</p>}
      {salida.estado === 'Efectiva' && <p className="text-sm">{t.effective(salida.fecha_efectiva, aplicacion.por_sistema)}</p>}
      {salida.estado === 'Cancelada' && salida.cancelacion && <p className="text-sm">{text.cancelled(salida.cancelacion.razon)}</p>}
      {salida.estado === 'IncidenciaDeAplicacion' && (
        <Alert>
          <p className="font-semibold">{t.incident}</p>
          {aplicacion.incidencia && <p>{t.incidentCodes[aplicacion.incidencia.codigo] ?? aplicacion.incidencia.detalle}</p>}
        </Alert>
      )}
      {aplicacion.intentos > 0 && salida.estado !== 'Efectiva' && <p className="text-[13px] text-muted">{t.attempts(aplicacion.intentos)}</p>}
      {salida.estado === 'Efectiva' && (
        <p className="text-sm">
          <Badge variant={salida.cuenta_estado === 'Coordinada' ? 'default' : salida.cuenta_estado === 'Incidencia' ? 'danger' : 'warning'}>{offboardingText.cuenta[salida.cuenta_estado]}</Badge>
        </p>
      )}
      <p className="text-[13px] text-muted">{t.accountNote}</p>
      {salida.acciones.aplicar && (
        <div>
          <Button onClick={onAplicar}>{salida.estado === 'IncidenciaDeAplicacion' ? t.retry : t.apply}</Button>
        </div>
      )}
      {(salida.estado === 'Programada' || salida.estado === 'IncidenciaDeAplicacion') && !salida.acciones.aplicar && <p className="text-[13px] text-muted">{t.notYet}</p>}
    </Seccion>
  )
}

function Checklist({ salida, onCompletar }: { salida: DetalleDeSalida; onCompletar: (tarea: TareaDeSalida) => void }) {
  const t = offboardingText.checklist

  return (
    <Seccion titulo={text.sections.checklist}>
      {salida.estado === 'Efectiva' && salida.cierre.estado === 'Pendiente' && <p className="text-[13px] text-muted">{t.afterEffective}</p>}
      <div className="overflow-x-auto rounded-lg border border-border">
        <table aria-label={t.tableLabel} className="w-full min-w-[640px] border-collapse text-left text-sm">
          <thead className="bg-soft text-[13px] text-muted">
            <tr>
              <th scope="col" className="px-3 py-2 font-semibold">{t.columns.order}</th>
              <th scope="col" className="px-3 py-2 font-semibold">{t.columns.task}</th>
              <th scope="col" className="px-3 py-2 font-semibold">{t.columns.evidence}</th>
              <th scope="col" className="px-3 py-2 font-semibold">{t.columns.status}</th>
              <th scope="col" className="px-3 py-2 font-semibold"><span className="sr-only">Acciones</span></th>
            </tr>
          </thead>
          <tbody>
            {salida.tareas.map((tarea) => (
              <tr key={tarea.id} className="border-t border-border align-top">
                <td className="px-3 py-2">{tarea.orden}</td>
                <td className="px-3 py-2">
                  <span className="font-semibold">{tarea.nombre}</span>
                  {tarea.tipo === 'Entrevista' ? <span className="block text-[13px] text-muted">{t.interviewTask}</span> : tarea.descripcion && <span className="block text-[13px] text-muted">{tarea.descripcion}</span>}
                </td>
                <td className="px-3 py-2">
                  {tarea.tipo === 'Entrevista' ? offboardingText.none : tarea.exige_evidencia ? t.required : t.optional}
                  {tarea.respaldos.map((respaldo) => (
                    <span key={respaldo.id} className={`block text-[13px] ${respaldo.disponible ? 'text-muted' : 'text-danger'}`}>
                      {respaldo.disponible ? `Respaldo #${respaldo.id}` : t.evidenceMissing}
                    </span>
                  ))}
                </td>
                <td className="px-3 py-2">
                  <Badge variant={tarea.estado === 'Completada' ? 'default' : 'warning'}>{tarea.estado === 'Completada' ? t.completed : t.pending}</Badge>
                  {tarea.estado === 'Completada' && <span className="block text-[13px] text-muted">{t.byOn(tarea.completada_por?.nombre ?? null, momentoLegible(tarea.completada_en))}</span>}
                </td>
                <td className="px-3 py-2 text-right">
                  {tarea.tipo === 'Plantilla' && tarea.estado === 'Pendiente' && salida.acciones.completar_tareas && (
                    <Button variant="secondary" onClick={() => onCompletar(tarea)} aria-label={t.completeTitle(tarea.nombre)}>
                      {t.complete}
                    </Button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Seccion>
  )
}

function PanelDeCierre({ salida, onCerrar }: { salida: DetalleDeSalida; onCerrar: () => void }) {
  const t = offboardingText.closing

  return (
    <Seccion titulo={text.sections.closing}>
      <p className="text-sm text-muted">{t.independent}</p>
      {salida.cierre.estado === 'Cerrado' ? (
        <p className="text-sm">{t.closed(salida.cierre.por?.nombre ?? null, salida.cierre.en ? momentoLegible(salida.cierre.en) : null)}</p>
      ) : salida.estado === 'Cancelada' ? null : (
        <>
          <p className="text-sm">{salida.cierre.bloqueos.length === 0 ? t.ready : t.pending}</p>
          {salida.cierre.bloqueos.length > 0 && (
            <div>
              <p className="text-[13px] font-semibold text-muted">{t.blockers}</p>
              <ul className="list-disc pl-5 text-sm">
                {salida.cierre.bloqueos.map((bloqueo) => (
                  <li key={bloqueo}>{offboardingText.bloqueos[bloqueo]}</li>
                ))}
              </ul>
            </div>
          )}
          <div>
            <Button onClick={onCerrar} disabled={!salida.acciones.cerrar}>
              {t.confirm}
            </Button>
          </div>
        </>
      )}
    </Seccion>
  )
}

function SeccionDeEntrevista({ salida, onNoRealizada }: { salida: DetalleDeSalida; onNoRealizada: () => void }) {
  const t = offboardingText.interview
  const [verRespuestas, setVerRespuestas] = useState(false)
  const { entrevista } = salida

  return (
    <Seccion titulo={text.sections.interview}>
      <p className="text-sm text-muted">{t.voluntary}</p>
      <p className="flex flex-wrap items-center gap-2 text-sm">
        <span className="sr-only">{t.statusLabel}: </span>
        <EstadoDeLaEntrevista estado={entrevista.estado} />
        {entrevista.estado === 'NoRealizada' && entrevista.motivo_no_realizada && <span>{t.notDone(offboardingText.noRealizada[entrevista.motivo_no_realizada])}</span>}
        {entrevista.presentada_en && <span className="text-muted">{momentoLegible(entrevista.presentada_en)}</span>}
      </p>
      <div className="flex flex-wrap gap-2">
        {salida.acciones.no_realizada && (
          <Button variant="secondary" onClick={onNoRealizada}>
            {t.markNotDone}
          </Button>
        )}
        {entrevista.estado === 'Completada' && (
          <Button variant="secondary" onClick={() => setVerRespuestas((v) => !v)} aria-expanded={verRespuestas}>
            {verRespuestas ? t.hideAnswers : t.viewAnswers}
          </Button>
        )}
      </div>
      {verRespuestas && <RespuestasConsultadas id={salida.id} />}
    </Seccion>
  )
}

function Historial({ salida }: { salida: DetalleDeSalida }) {
  const t = offboardingText.history

  return (
    <Seccion titulo={text.sections.history}>
      {salida.historial.length === 0 ? (
        <p className="text-sm text-muted">{t.empty}</p>
      ) : (
        <ol className="flex flex-col gap-2 text-sm">
          {salida.historial.map((evento, indice) => (
            <li key={`${evento.evento}-${indice}`} className="flex flex-wrap gap-x-3 border-b border-border pb-2">
              <span className="font-semibold">{offboardingText.eventos[evento.evento] ?? evento.evento}</span>
              <span className="text-muted">{evento.actor.tipo === 'sistema' ? t.system : (evento.actor.nombre ?? offboardingText.none)}</span>
              <span className="text-muted">{momentoLegible(evento.en)}</span>
            </li>
          ))}
        </ol>
      )}
    </Seccion>
  )
}

/** `/offboarding/:id`: expediente de una salida con aplicación, checklist, cierre y entrevista (solo Administrador). */
export function ExpedientePage() {
  const { id } = useParams()
  const salidaId = Number(id)
  const salida = useSalida(salidaId)
  const aviso = (useLocation().state as { aviso?: string } | null)?.aviso
  const accion = useAccionDeSalida(salidaId)
  const completar = useCompletarTarea(salidaId)
  const claveDe = useClaveDeOperacion()
  const [dialogo, setDialogo] = useState<Dialogo>(null)
  const [problema, setProblema] = useState<ProblemaDeSalida | null>(null)
  const [mensaje, setMensaje] = useState<string | null>(null)

  if (salida.isPending) {
    return (
      <div role="status" aria-label={text.loading} className="flex flex-col gap-3">
        <Skeleton className="h-10" />
        <Skeleton className="h-40" />
      </div>
    )
  }
  if (salida.isError) {
    const noEncontrado = problemaDeSalida(salida.error).noEncontrado
    return (
      <div className="rounded-xl border border-border bg-surface">
        {noEncontrado ? (
          <EmptyState icon={<IconFileText size={24} />} title={text.notFoundTitle} description={text.notFoundDescription} />
        ) : (
          <ErrorState title={text.errorTitle} description={text.errorDescription} retryLabel={offboardingText.retry} onRetry={() => void salida.refetch()} />
        )}
      </div>
    )
  }

  const datos = salida.data
  const cerrarDialogo = () => {
    setDialogo(null)
    setProblema(null)
  }
  const alExito = (mensajeDeExito: string) => {
    cerrarDialogo()
    setMensaje(mensajeDeExito)
  }
  const alFallar = (error: unknown) => {
    const p = problemaDeSalida(error)
    setProblema(p)
    if (p.incidencia || p.noEncontrado) void salida.refetch()
  }
  const ejecutar = (nombre: AccionDeSalida, cuerpo: Omit<CuerpoDeAccion, 'version'>, exito: string) => {
    const completo = { version: nombre === 'entrevista/no-realizada' ? datos.entrevista.version : datos.version, ...cuerpo }
    accion.mutate({ accion: nombre, cuerpo: completo, clave: claveDe(JSON.stringify([nombre, completo])) }, { onSuccess: () => alExito(exito), onError: alFallar })
  }
  const completarTarea = (tarea: TareaDeSalida, documentoIds: number[]) => {
    const firma = JSON.stringify([tarea.id, datos.version, documentoIds])
    completar.mutate({ tareaId: tarea.id, version: datos.version, documentoIds, clave: claveDe(firma) }, { onSuccess: () => alExito(offboardingText.checklist.completeConfirm), onError: alFallar })
  }

  const errorDelDialogo = problema && !problema.conflicto ? problema.mensaje : undefined

  return (
    <div className="flex flex-col gap-5">
      <Breadcrumb label="Ruta de navegación" items={[{ label: offboardingText.list.title, to: '/offboarding' }, { label: text.breadcrumb }]} />

      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{text.title(datos.colaborador.nombre ?? offboardingText.none)}</h1>
          <p className="text-muted">
            <Link to={`/colaboradores/${datos.colaborador.id}`} className="text-accent underline">
              {datos.colaborador.codigo}
            </Link>
            {' · '}
            {offboardingText.motivos[datos.motivo.clave]} · {text.effective(datos.fecha_efectiva)}
          </p>
          <p className="mt-1 flex flex-wrap items-center gap-2">
            <EstadoDeLaSalida estado={datos.estado} />
            {datos.estado !== 'Cancelada' && <EstadoDelCierre estado={datos.cierre.estado} />}
            <EstadoDeLaEntrevista estado={datos.entrevista.estado} />
          </p>
          <p className="mt-1 text-[13px] text-muted">
            {text.formalizedBy(datos.formalizado.por.nombre, momentoLegible(datos.formalizado.en))} · {text.policyVersion(datos.politica.numero)}
          </p>
        </div>
        {datos.acciones.cancelar && (
          <Button variant="danger" onClick={() => setDialogo({ tipo: 'cancelar' })}>
            {text.cancel}
          </Button>
        )}
      </header>

      {aviso && mensaje === null && <Alert tone="neutral">{aviso}</Alert>}
      {mensaje && <Alert tone="neutral">{mensaje}</Alert>}
      {problema?.conflicto && (
        <Alert>
          <p className="font-semibold">{offboardingText.errors.conflictTitle}</p>
          <p>{offboardingText.errors.conflict}</p>
          <Button
            variant="secondary"
            onClick={() => {
              void salida.refetch()
              cerrarDialogo()
            }}
          >
            {offboardingText.refresh}
          </Button>
        </Alert>
      )}

      <PanelDeAplicacion salida={datos} onAplicar={() => setDialogo({ tipo: 'aplicar' })} />
      <Checklist salida={datos} onCompletar={(tarea) => setDialogo({ tipo: 'tarea', tarea })} />
      <PanelDeCierre salida={datos} onCerrar={() => setDialogo({ tipo: 'cerrar' })} />
      <SeccionDeEntrevista salida={datos} onNoRealizada={() => setDialogo({ tipo: 'no-realizada' })} />
      <Historial salida={datos} />

      <DialogoDeCancelacion open={dialogo?.tipo === 'cancelar'} busy={accion.isPending} error={errorDelDialogo} onCancel={cerrarDialogo} onConfirm={(razon) => ejecutar('cancelacion', { razon }, text.cancelConfirm)} />
      <DialogoDeConfirmacion open={dialogo?.tipo === 'aplicar'} busy={accion.isPending} error={errorDelDialogo} onCancel={cerrarDialogo} titulo={offboardingText.application.applyTitle} descripcion={offboardingText.application.applyDescription} accion={offboardingText.application.applyConfirm} onConfirm={() => ejecutar('aplicacion', {}, offboardingText.application.effective(datos.fecha_efectiva, false))} />
      <DialogoDeConfirmacion open={dialogo?.tipo === 'cerrar'} busy={accion.isPending} error={errorDelDialogo} onCancel={cerrarDialogo} titulo={offboardingText.closing.confirmTitle} descripcion={offboardingText.closing.confirmDescription} accion={offboardingText.closing.confirmAction} onConfirm={() => ejecutar('cierre', {}, offboardingText.closing.confirmAction)} />
      <DialogoDeNoRealizada open={dialogo?.tipo === 'no-realizada'} busy={accion.isPending} error={errorDelDialogo} onCancel={cerrarDialogo} onConfirm={(motivo: MotivoDeNoRealizada) => ejecutar('entrevista/no-realizada', { motivo }, offboardingText.interview.notDoneConfirm)} />
      {dialogo?.tipo === 'tarea' && (
        <DialogoDeTarea open busy={completar.isPending} error={errorDelDialogo} onCancel={cerrarDialogo} tarea={dialogo.tarea} colaboradorId={datos.colaborador.id} onConfirm={(documentoIds) => completarTarea(dialogo.tarea, documentoIds)} />
      )}
    </div>
  )
}
