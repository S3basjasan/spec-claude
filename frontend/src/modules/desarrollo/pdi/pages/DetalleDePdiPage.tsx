import { useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { Tabs, type TabItem } from '../../../../shared/ui/Tabs'
import type { Accion, EstadoAccion, Pdi, PestanaDetalle, ResultadoCompromiso, ResultadoDeCierre } from '../api'
import { BadgeDeEstadoDePdi, BadgeDeResultado, BadgeDeTipoDePdi, BarraDeAvance } from '../components/Etiquetas'
import { CompromisosYAcciones } from '../components/CompromisosYAcciones'
import { HistorialDePdi } from '../components/HistorialDePdi'
import { HitosDelPlan } from '../components/HitosDelPlan'
import { ModalDeCierre } from '../components/ModalDeCierre'
import { ModalDeMotivo } from '../components/ModalDeMotivo'
import { ModalDeResponsable } from '../components/ModalDeResponsable'
import { PanelDeAcuerdo } from '../components/PanelDeAcuerdo'
import { SelectorDeAccionVinculable } from '../components/SelectorDeAccionVinculable'
import { SinAccesoPdi } from '../components/RequirePermiso'
import { VistaDeMiPlan } from '../components/VistaDeMiPlan'
import { fechaConHora, rangoDeFechas } from '../fecha'
import { useAvanceDeAccion, useAvanceDeHito, useCambiarResponsable, useCerrarPdi, useConfirmarAcuerdo, useDevolverABorrador, useVincularAccion } from '../hooks/useMutacionesPdi'
import { useOpciones, usePdi, usePermisosPdi } from '../hooks/usePdi'
import { PERMISOS, tieneAlgunaDeGestion, tienePermiso } from '../permisos'
import { pdiText } from '../text'

const t = pdiText.detalle
const PESTANAS: PestanaDetalle[] = ['resumen', 'compromisos', 'hitos', 'historial']

/** Detalle de un plan. El administrador ve cuatro pestañas y los botones que su permiso y el estado permiten; el colaborador, su vista de solo lectura. */
export function DetalleDePdiPage() {
  const { id } = useParams()
  const planId = id === undefined ? null : Number(id)
  const plan = usePdi(planId)
  const { data: permisos, isPending: cargandoPermisos } = usePermisosPdi()
  const migas = <Breadcrumb label={t.volver} items={[{ label: t.volver, to: '/pdi' }, { label: plan.data?.codigo ?? t.cargando }]} />

  if (plan.isPending || cargandoPermisos) {
    return (
      <div className="mx-auto flex max-w-[1200px] flex-col gap-5">
        {migas}
        <div role="status" aria-label={t.cargando} className="flex flex-col gap-2">
          <Skeleton className="h-16" />
          <Skeleton className="h-40" />
        </div>
      </div>
    )
  }

  if (plan.isError) {
    const noExiste = toApiError(plan.error).status === 404

    return (
      <div className="mx-auto flex max-w-[1200px] flex-col gap-5">
        {migas}
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={noExiste ? t.noEncontrado : t.error.titulo} description={noExiste ? '' : t.error.descripcion} retryLabel={t.error.reintentar} onRetry={() => void plan.refetch()} />
        </div>
      </div>
    )
  }

  if (!tieneAlgunaDeGestion(permisos)) {
    if (!tienePermiso(permisos, PERMISOS.verPropio)) return <SinAccesoPdi />

    return (
      <div className="mx-auto flex max-w-[1200px] flex-col gap-5">
        {migas}
        <VistaDeMiPlan plan={plan.data} />
      </div>
    )
  }

  return <DetalleDelAdministrador pdi={plan.data} migas={migas} permisos={permisos ?? []} />
}

function DetalleDelAdministrador({ pdi, migas, permisos }: { pdi: Pdi; migas: ReactNode; permisos: readonly string[] }) {
  const navegar = useNavigate()
  const [params, setParams] = useSearchParams()
  const pedida = params.get('pestana')
  const activa: PestanaDetalle = PESTANAS.find((p) => p === pedida) ?? 'resumen'
  const opciones = useOpciones('')

  const confirmar = useConfirmarAcuerdo(pdi.id)
  const devolver = useDevolverABorrador(pdi.id)
  const responsable = useCambiarResponsable(pdi.id)
  const cerrar = useCerrarPdi(pdi.id)
  const avanceAccion = useAvanceDeAccion()
  const avanceHito = useAvanceDeHito()
  const vincular = useVincularAccion()

  const [mensaje, setMensaje] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [conflicto, setConflicto] = useState(false)
  const [modal, setModal] = useState<'devolver' | 'cerrar' | 'responsable' | null>(null)
  const [vinculando, setVinculando] = useState<Accion | null>(null)
  const [cierre, setCierre] = useState<ResultadoDeCierre | null>(null)
  const [errorModal, setErrorModal] = useState<string | null>(null)

  const puede = (clave: (typeof PERMISOS)[keyof typeof PERMISOS]) => permisos.includes(clave)
  const a = pdi.acciones_permitidas
  const cerrado = pdi.estado === 'Cerrado'

  const manejar = (e: unknown): string | null => {
    const api = toApiError(e)
    if (api.status === 409) {
      setConflicto(true)
      return null
    }
    const detalles = Object.values(api.fieldErrors).flat()

    return detalles.length > 0 ? detalles.join(' ') : (api.message ?? t.errorAccion)
  }

  const ejecutar = async (accion: () => Promise<unknown>, exito: string | null) => {
    setError(null)
    setMensaje(null)
    setConflicto(false)
    try {
      await accion()
      if (exito !== null) setMensaje(exito)
    } catch (e) {
      setError(manejar(e))
    }
  }

  const items: TabItem[] = PESTANAS.map((p) => ({ id: p, label: pdiText.pestanasDetalle[p] }))
  const destino = pdi.puesto_objetivo?.nombre ?? pdiText.destino(null)

  return (
    <div className="mx-auto flex max-w-[1200px] flex-col gap-5">
      {migas}

      <header className="flex flex-col gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <h1 className="m-0 text-2xl font-bold">{pdi.colaborador.nombre}</h1>
          <BadgeDeTipoDePdi valor={pdi.tipo} />
          <BadgeDeEstadoDePdi valor={pdi.estado} />
          {pdi.resultado !== null && <BadgeDeResultado valor={pdi.resultado} />}
        </div>
        <p className="m-0 text-muted">
          {pdi.codigo}
          {pdi.colaborador.departamento !== null && ` · ${pdi.colaborador.departamento}`} · {t.destino}: {destino} · {t.responsable}: {pdi.responsable.nombre} · {rangoDeFechas(pdi.fecha_inicio, pdi.fecha_fin)}
        </p>
        <BarraDeAvance valor={pdi.avance} />
      </header>

      {conflicto && (
        <Alert>
          {t.conflicto}{' '}
          <Link to={`/pdi/${pdi.id}`} reloadDocument className="font-semibold underline">
            {t.recargar}
          </Link>
        </Alert>
      )}
      {error !== null && <Alert>{error}</Alert>}
      {mensaje !== null && (
        <div role="status" className="rounded-lg bg-soft px-4 py-2.5 text-[15px] font-semibold text-accent">
          {mensaje}
        </div>
      )}
      {pdi.responsable.sin_permiso && <Alert tone="neutral">{t.responsableSinPermiso}</Alert>}

      <Tabs label={pdiText.titulo} tabs={items} active={activa} onChange={(p) => setParams(p === 'resumen' ? {} : { pestana: p }, { replace: true })} idBase="pdi-detalle" scrollable>
        {activa === 'resumen' && (
          <div className="flex flex-col gap-4">
            <dl className="m-0 grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2">
              <div className="sm:col-span-2">
                <dt className="text-[13px] text-muted">{t.objetivo}</dt>
                <dd className="m-0 font-semibold">{pdi.objetivo}</dd>
              </div>
              <div>
                <dt className="text-[13px] text-muted">{t.creadoPor}</dt>
                <dd className="m-0">
                  {pdi.creado_por} · {fechaConHora(pdi.creado_en)}
                </dd>
              </div>
              {pdi.origen !== null && (
                <div>
                  <dt className="text-[13px] text-muted">{t.origen}</dt>
                  <dd className="m-0">{pdi.origen.tipo === 'dnf' ? pdiText.asistente.paso1.origenDnf : pdiText.asistente.paso1.origenAdecuacion}</dd>
                </div>
              )}
            </dl>

            <PanelDeAcuerdo pdi={pdi} />

            {a.acordar && pdi.completitud.length > 0 && (
              <Alert tone="neutral">
                <ul className="m-0 list-inside list-disc">
                  {pdi.completitud.map((c) => (
                    <li key={c}>{t.faltantes[c] ?? c}</li>
                  ))}
                </ul>
              </Alert>
            )}

            <div className="flex flex-wrap gap-3">
              {a.editar && puede(PERMISOS.gestionar) && (
                <Button variant="secondary" onClick={() => navegar(`/pdi/${pdi.id}/editar`)}>
                  {t.botones.editar}
                </Button>
              )}
              {a.acordar && puede(PERMISOS.acordar) && (
                <Button loading={confirmar.isPending} disabled={pdi.completitud.length > 0} onClick={() => void ejecutar(() => confirmar.mutateAsync(pdi.version), t.confirmarMensaje)}>
                  {t.botones.confirmarAcuerdo}
                </Button>
              )}
              {a.devolver && puede(PERMISOS.gestionar) && (
                <Button variant="secondary" onClick={() => { setErrorModal(null); setModal('devolver') }}>
                  {t.botones.devolver}
                </Button>
              )}
              {a.cerrar && puede(PERMISOS.cerrar) && (
                <Button onClick={() => { setErrorModal(null); setModal('cerrar') }}>{t.botones.cerrar}</Button>
              )}
              {a.cambiar_responsable && puede(PERMISOS.gestionar) && (
                <Button variant="secondary" onClick={() => { setErrorModal(null); setModal('responsable') }}>
                  {t.botones.cambiarResponsable}
                </Button>
              )}
            </div>

            {cerrado && (
              <section aria-label={t.cierre.titulo} className="flex flex-col gap-2 rounded-xl border border-border bg-surface p-4">
                <h3 className="m-0 text-base font-bold">{t.cierre.titulo}</h3>
                <p className="m-0">
                  {pdi.resultado !== null && <BadgeDeResultado valor={pdi.resultado} />} {t.cierre.cerradoEl} {fechaConHora(pdi.cerrado_en)}
                </p>
                {pdi.conclusion !== null && <p className="m-0">{pdi.conclusion}</p>}
                {(cierre?.ofrecer_adecuacion ?? (pdi.tipo !== 'Mejora' && pdi.puesto_objetivo !== null ? { colaborador_id: pdi.colaborador.id, puesto_objetivo_id: pdi.puesto_objetivo.id } : null)) !== null && (
                  <p className="m-0 flex flex-wrap items-center gap-3">
                    <Link
                      to={`/adecuacion?colaborador=${pdi.colaborador.id}&puesto=${pdi.puesto_objetivo?.id ?? ''}`}
                      className="inline-flex min-h-11 items-center rounded-lg border border-border px-4 font-semibold hover:bg-soft"
                    >
                      {t.generarAdecuacion}
                    </Link>
                    <span className="text-[13px] text-muted">{t.generarAdecuacionAyuda}</span>
                  </p>
                )}
              </section>
            )}
          </div>
        )}

        {activa === 'compromisos' && (
          <CompromisosYAcciones
            compromisos={pdi.compromisos}
            puedeRegistrarAvance={a.avance && puede(PERMISOS.seguimiento)}
            puedeVincular={a.vincular && puede(PERMISOS.vincular)}
            busy={avanceAccion.isPending || vincular.isPending}
            onAvance={(accion, estado: EstadoAccion) => void ejecutar(() => avanceAccion.mutateAsync({ accionId: accion.id, estado }), null)}
            onVincular={(accion) => { setErrorModal(null); setVinculando(accion) }}
            onDesvincular={(accion) => void ejecutar(() => vincular.mutateAsync({ accionId: accion.id, planAccionId: null, version: pdi.estado === 'Borrador' ? pdi.version : null }), null)}
          />
        )}

        {activa === 'hitos' && (
          <HitosDelPlan
            hitos={pdi.hitos}
            puedeRegistrarAvance={a.avance && puede(PERMISOS.seguimiento)}
            busy={avanceHito.isPending}
            onGuardar={(hitoId, estado, observaciones) => void ejecutar(() => avanceHito.mutateAsync({ hitoId, estado, observaciones }), null)}
          />
        )}

        {activa === 'historial' && <HistorialDePdi pdiId={pdi.id} />}
      </Tabs>

      <ModalDeMotivo
        open={modal === 'devolver'}
        titulo={pdiText.modales.motivo.titulo}
        descripcion={pdiText.modales.motivo.descripcion}
        etiqueta={pdiText.modales.motivo.etiqueta}
        obligatorio={pdiText.modales.motivo.obligatorio}
        confirmar={pdiText.modales.motivo.confirmar}
        busy={devolver.isPending}
        error={errorModal}
        onConfirm={(motivo) => {
          setErrorModal(null)
          devolver.mutate(
            { motivo, version: pdi.version },
            { onSuccess: () => { setModal(null); setMensaje(t.devueltoMensaje) }, onError: (e) => setErrorModal(manejar(e)) },
          )
        }}
        onClose={() => setModal(null)}
      />

      <ModalDeCierre
        key={`cierre-${pdi.version}`}
        open={modal === 'cerrar'}
        pdi={pdi}
        busy={cerrar.isPending}
        error={errorModal}
        onConfirm={(resultados: { compromiso_id: number; resultado: ResultadoCompromiso }[], conclusion) => {
          setErrorModal(null)
          cerrar.mutate(
            { resultados, conclusion, version: pdi.version },
            { onSuccess: (resultado) => { setCierre(resultado); setModal(null); setMensaje(t.cerradoMensaje) }, onError: (e) => setErrorModal(manejar(e)) },
          )
        }}
        onClose={() => setModal(null)}
      />

      <ModalDeResponsable
        key={`responsable-${pdi.responsable.id}`}
        open={modal === 'responsable'}
        responsables={opciones.data?.responsables ?? []}
        actualId={pdi.responsable.id}
        busy={responsable.isPending}
        error={errorModal}
        onConfirm={(responsableId) => {
          setErrorModal(null)
          responsable.mutate(
            { responsableId, version: pdi.version },
            { onSuccess: () => { setModal(null); setMensaje(t.responsableMensaje) }, onError: (e) => setErrorModal(manejar(e)) },
          )
        }}
        onClose={() => setModal(null)}
      />

      <SelectorDeAccionVinculable
        open={vinculando !== null}
        colaboradorId={pdi.colaborador.id}
        actual={vinculando?.vinculo?.plan_accion_id ?? null}
        error={errorModal}
        busy={vincular.isPending}
        onElegir={(elegida) => {
          if (vinculando === null) return
          setErrorModal(null)
          vincular.mutate(
            { accionId: vinculando.id, planAccionId: elegida.plan_accion_id, version: pdi.estado === 'Borrador' ? pdi.version : null },
            { onSuccess: () => setVinculando(null), onError: (e) => setErrorModal(manejar(e) ?? pdiText.vinculables.errorVincular) },
          )
        }}
        onClose={() => setVinculando(null)}
      />
    </div>
  )
}
