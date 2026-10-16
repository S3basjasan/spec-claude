import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import type { EstadoDestino, Puede } from '../api'
import { useCambiarEstado } from '../hooks/useCambiarEstado'
import { planText } from '../text'
import { CambiarAprobadorDialog } from './CambiarAprobadorDialog'
import { FaltantesDialog, type Faltante } from './FaltantesDialog'
import { MotivoDialog } from './MotivoDialog'

interface AccionesPlanProps {
  planId: number
  nombre: string
  puede: Puede
  /** `fila`: tabla de planes (con «Ver detalle»); `detalle`: página del plan (con «Cambiar aprobador»). */
  modo?: 'fila' | 'detalle'
  aprobadorId?: number | null
}

type Dialogo = 'aprobar' | 'iniciar' | 'cerrar' | 'devolver' | 'aprobador' | null

const enlace = 'inline-flex min-h-11 items-center px-3 font-semibold text-accent underline'
const t = planText.botones

/**
 * Botones de un plan: dependen del estado y del usuario (campo `puede`, que calcula el servidor). Aprobar, iniciar y
 * cerrar piden confirmación; devolver pide un motivo; enviar a revisión lista lo que falta si el plan está incompleto.
 */
export function AccionesPlan({ planId, nombre, puede, modo = 'fila', aprobadorId = null }: AccionesPlanProps) {
  const cambiar = useCambiarEstado()
  const [dialogo, setDialogo] = useState<Dialogo>(null)
  const [error, setError] = useState<string | undefined>()
  const [faltantes, setFaltantes] = useState<Faltante[] | null>(null)

  const cerrar = () => {
    setDialogo(null)
    setError(undefined)
  }

  const ejecutar = async (estado: EstadoDestino, motivo?: string) => {
    setError(undefined)
    try {
      await cambiar.mutateAsync({ id: planId, estado, motivo })
      setDialogo(null)
    } catch (e) {
      const api = toApiError(e)
      if (api.code === 'plan_incompleto' && Array.isArray(api.data?.faltantes)) {
        setDialogo(null)
        setFaltantes(api.data.faltantes as Faltante[])
        return
      }
      setError(planText.transicion.erroresGenericos[api.code ?? ''] ?? api.message ?? planText.error.generico)
    }
  }

  const etiqueta = (texto: string) => t.de(texto, nombre)
  const ocupado = cambiar.isPending
  const confirmacion = dialogo === 'aprobar' ? planText.transicion.aprobar : dialogo === 'iniciar' ? planText.transicion.iniciar : planText.transicion.cerrar
  const destinoConfirmacion: EstadoDestino = dialogo === 'aprobar' ? 'Aprobado' : dialogo === 'iniciar' ? 'Ejecutando' : 'Cerrado'

  return (
    <div className="flex flex-col items-end gap-1">
      <div className="flex flex-wrap justify-end gap-1">
        {puede.editar && (
          <Link to={`/plan-formacion/${planId}/editar`} aria-label={etiqueta(t.editar)} className={enlace}>
            {t.editar}
          </Link>
        )}
        {puede.enviar_revision && (
          <Button variant="secondary" aria-label={etiqueta(t.enviarRevision)} loading={ocupado} onClick={() => void ejecutar('Revision')}>
            {t.enviarRevision}
          </Button>
        )}
        {puede.aprobar && (
          <Button aria-label={etiqueta(t.aprobar)} onClick={() => setDialogo('aprobar')}>
            {t.aprobar}
          </Button>
        )}
        {puede.devolver && (
          <Button variant="secondary" aria-label={etiqueta(t.devolver)} onClick={() => setDialogo('devolver')}>
            {t.devolver}
          </Button>
        )}
        {puede.iniciar && (
          <Button aria-label={etiqueta(t.iniciar)} onClick={() => setDialogo('iniciar')}>
            {t.iniciar}
          </Button>
        )}
        {puede.cerrar && (
          <Button aria-label={etiqueta(t.cerrar)} onClick={() => setDialogo('cerrar')}>
            {t.cerrar}
          </Button>
        )}
        {modo === 'detalle' && puede.reasignar_aprobador && (
          <Button variant="secondary" onClick={() => setDialogo('aprobador')}>
            {t.cambiarAprobador}
          </Button>
        )}
        {modo === 'fila' && (
          <Link to={`/plan-formacion/${planId}`} aria-label={etiqueta(t.verDetalle)} className={enlace}>
            {t.verDetalle}
          </Link>
        )}
      </div>
      {error && dialogo === null && (
        <p role="alert" className="text-[13px] text-danger">
          {error}
        </p>
      )}

      <ConfirmDialog
        open={dialogo === 'aprobar' || dialogo === 'iniciar' || dialogo === 'cerrar'}
        title={confirmacion.title}
        description={confirmacion.description}
        confirmLabel={confirmacion.confirmar}
        cancelLabel={planText.transicion.cancelar}
        tone="primary"
        busy={ocupado}
        error={error}
        onCancel={cerrar}
        onConfirm={() => void ejecutar(destinoConfirmacion)}
      />
      <MotivoDialog
        open={dialogo === 'devolver'}
        title={planText.transicion.devolver.title}
        description={planText.transicion.devolver.description}
        label={planText.transicion.devolver.motivo}
        requeridoMensaje={planText.transicion.devolver.motivoRequerido}
        confirmLabel={planText.transicion.devolver.confirmar}
        cancelLabel={planText.transicion.cancelar}
        busy={ocupado}
        error={error}
        onCancel={cerrar}
        onConfirm={(motivo) => void ejecutar('Borrador', motivo)}
      />
      <CambiarAprobadorDialog open={dialogo === 'aprobador'} planId={planId} aprobadorActual={aprobadorId} onClose={cerrar} />
      <FaltantesDialog planId={planId} faltantes={faltantes} onClose={() => setFaltantes(null)} />
    </div>
  )
}
