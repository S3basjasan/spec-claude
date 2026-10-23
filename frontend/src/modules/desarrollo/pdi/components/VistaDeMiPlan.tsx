import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import type { Pdi } from '../api'
import { rangoDeFechas } from '../fecha'
import { useAceptarPdi, useDevolverDelColaborador } from '../hooks/useMutacionesPdi'
import { pdiText } from '../text'
import { CompromisosYAcciones } from './CompromisosYAcciones'
import { BadgeDeEstadoDePdi, BadgeDeResultado, BadgeDeTipoDePdi, BarraDeAvance } from './Etiquetas'
import { HitosDelPlan } from './HitosDelPlan'
import { ModalDeMotivo } from './ModalDeMotivo'
import { PanelDeAcuerdo } from './PanelDeAcuerdo'

const t = pdiText.mi

interface VistaDeMiPlanProps {
  plan: Pdi
  /** Se llama tras aceptar o devolver, p. ej. para quitar el plan de la pantalla. */
  onRespondido?: (resultado: 'aceptado' | 'devuelto') => void
}

/** Detalle de un plan para su colaborador: solo lectura, con «Aceptar plan» y «Devolver con comentario» en un Borrador con acuerdo. */
export function VistaDeMiPlan({ plan, onRespondido }: VistaDeMiPlanProps) {
  const aceptar = useAceptarPdi(plan.id)
  const devolver = useDevolverDelColaborador(plan.id)
  const [leido, setLeido] = useState(false)
  const [devolviendo, setDevolviendo] = useState(false)
  const [mensaje, setMensaje] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const puede = plan.acciones_permitidas.aceptar

  const mensajeDeError = (e: unknown): string => {
    const api = toApiError(e)

    return api.status === 404 || api.status === 409 ? t.noDisponible : (api.message ?? pdiText.detalle.errorAccion)
  }

  const confirmarAceptacion = async () => {
    setError(null)
    try {
      await aceptar.mutateAsync(plan.version)
      setMensaje(t.aceptado)
      onRespondido?.('aceptado')
    } catch (e) {
      setError(mensajeDeError(e))
    }
  }

  const confirmarDevolucion = async (comentario: string) => {
    setError(null)
    try {
      await devolver.mutateAsync({ comentario, version: plan.version })
      setDevolviendo(false)
      setMensaje(t.devuelto)
      onRespondido?.('devuelto')
    } catch (e) {
      setError(mensajeDeError(e))
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <header className="flex flex-col gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="m-0 text-xl font-bold">{t.plan(pdiText.tipos[plan.tipo], plan.puesto_objetivo?.nombre ?? pdiText.destino(null))}</h2>
          <BadgeDeTipoDePdi valor={plan.tipo} />
          <BadgeDeEstadoDePdi valor={plan.estado} />
          {plan.resultado !== null && <BadgeDeResultado valor={plan.resultado} />}
        </div>
        <p className="m-0 text-muted">{plan.objetivo}</p>
        <dl className="m-0 grid gap-3 sm:grid-cols-3">
          <div>
            <dt className="text-[13px] text-muted">{pdiText.detalle.responsable}</dt>
            <dd className="m-0 font-semibold">{plan.responsable.nombre}</dd>
          </div>
          <div>
            <dt className="text-[13px] text-muted">{pdiText.detalle.periodo}</dt>
            <dd className="m-0 font-semibold">{rangoDeFechas(plan.fecha_inicio, plan.fecha_fin)}</dd>
          </div>
          <div>
            <dt className="text-[13px] text-muted">{pdiText.detalle.avance}</dt>
            <dd className="m-0">
              <BarraDeAvance valor={plan.avance} />
            </dd>
          </div>
        </dl>
      </header>

      {mensaje !== null && (
        <div role="status" className="rounded-lg bg-soft px-4 py-2.5 text-[15px] font-semibold text-accent">
          {mensaje}
        </div>
      )}
      {error !== null && <Alert>{error}</Alert>}

      <PanelDeAcuerdo pdi={plan} />

      {puede && (
        <section aria-label={t.acuerdo} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
          <label className="flex min-h-11 items-start gap-2 text-[15px]">
            <input type="checkbox" className="mt-1" checked={leido} onChange={(e) => setLeido(e.target.checked)} />
            {t.lectura}
          </label>
          <div className="flex flex-wrap gap-3">
            <Button loading={aceptar.isPending} disabled={!leido} onClick={() => void confirmarAceptacion()}>
              {t.aceptar}
            </Button>
            <Button variant="secondary" onClick={() => setDevolviendo(true)} disabled={aceptar.isPending}>
              {t.devolver}
            </Button>
          </div>
        </section>
      )}

      {plan.estado === 'Cerrado' && plan.conclusion !== null && (
        <p className="m-0 rounded-lg bg-bg p-3">
          <span className="font-semibold">{pdiText.detalle.conclusion}: </span>
          {plan.conclusion}
        </p>
      )}

      <section aria-label={pdiText.pestanasDetalle.compromisos} className="flex flex-col gap-3">
        <h3 className="m-0 text-lg font-bold">{pdiText.pestanasDetalle.compromisos}</h3>
        <CompromisosYAcciones compromisos={plan.compromisos} puedeRegistrarAvance={false} puedeVincular={false} />
      </section>
      <section aria-label={pdiText.pestanasDetalle.hitos} className="flex flex-col gap-3">
        <h3 className="m-0 text-lg font-bold">{pdiText.pestanasDetalle.hitos}</h3>
        <HitosDelPlan hitos={plan.hitos} puedeRegistrarAvance={false} />
      </section>

      <ModalDeMotivo
        open={devolviendo}
        titulo={pdiText.modales.comentario.titulo}
        descripcion={pdiText.modales.comentario.descripcion}
        etiqueta={pdiText.modales.comentario.etiqueta}
        obligatorio={pdiText.modales.comentario.obligatorio}
        confirmar={pdiText.modales.comentario.confirmar}
        busy={devolver.isPending}
        error={error}
        onConfirm={(c) => void confirmarDevolucion(c)}
        onClose={() => setDevolviendo(false)}
      />
    </div>
  )
}
