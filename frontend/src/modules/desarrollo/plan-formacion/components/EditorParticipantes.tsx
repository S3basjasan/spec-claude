import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import type { Accion, AvisoCruce } from '../api'
import { useCambiarParticipantes } from '../hooks/useCambiarParticipantes'
import { planText } from '../text'
import { SelectorParticipantes, type ParticipanteSeleccionado } from './SelectorParticipantes'

const t = planText.participantes

interface EditorParticipantesProps {
  /** Acción a editar; `null` cierra el editor. */
  accion: Accion | null
  onClose: () => void
}

/** Cambia los participantes de una acción de un plan Borrador, Aprobado o Ejecutando respetando la capacidad. */
export function EditorParticipantes({ accion, onClose }: EditorParticipantesProps) {
  if (accion === null) return null
  return <Contenido accion={accion} onClose={onClose} />
}

function Contenido({ accion, onClose }: { accion: Accion; onClose: () => void }) {
  const cambiar = useCambiarParticipantes()
  const [seleccion, setSeleccion] = useState<ParticipanteSeleccionado[]>(accion.participantes.map((p) => ({ id: p.colaborador_id, nombre: p.nombre })))
  const [error, setError] = useState<string | undefined>()
  const [avisos, setAvisos] = useState<AvisoCruce[] | null>(null)

  const guardar = async () => {
    setError(undefined)
    try {
      const resultado = await cambiar.mutateAsync({ accionId: accion.id, colaboradorIds: seleccion.map((p) => p.id) })
      if (resultado.avisos_cruce.length === 0) onClose()
      else setAvisos(resultado.avisos_cruce)
    } catch (e) {
      const api = toApiError(e)
      setError(t.errores[api.code ?? ''] ?? api.message ?? planText.error.generico)
    }
  }

  const sinVinculo = new Set(accion.participantes.filter((p) => !p.vinculo_vigente).map((p) => p.colaborador_id))

  return (
    <Modal
      open
      size="lg"
      title={t.title(accion.nombre)}
      onClose={onClose}
      busy={cambiar.isPending}
      footer={
        avisos === null ? (
          <>
            <Button variant="secondary" onClick={onClose} disabled={cambiar.isPending}>
              {t.cancelar}
            </Button>
            <Button loading={cambiar.isPending} onClick={() => void guardar()}>
              {t.guardar}
            </Button>
          </>
        ) : (
          <Button onClick={onClose}>{planText.acciones.detalle.cerrar}</Button>
        )
      }
    >
      <div className="flex flex-col gap-4">
        {error && <Alert>{error}</Alert>}
        {avisos === null ? (
          <SelectorParticipantes idBase={`accion-${accion.id}`} capacidad={accion.capacidad_maxima} seleccionados={seleccion} sinVinculo={sinVinculo} onChange={setSeleccion} />
        ) : (
          <div role="status" className="flex flex-col gap-2">
            <p className="font-semibold text-accent">{t.guardado}</p>
            <div className="rounded-lg bg-warnsoft p-3 text-warn">
              <p className="font-semibold">{planText.detalle.cruces}</p>
              <ul className="mt-1 list-inside list-disc text-[15px]">
                {Array.from(new Set(avisos.map((a) => a.nombre))).map((nombre) => (
                  <li key={nombre}>{planText.detalle.cruceItem(nombre)}</li>
                ))}
              </ul>
            </div>
          </div>
        )}
      </div>
    </Modal>
  )
}
