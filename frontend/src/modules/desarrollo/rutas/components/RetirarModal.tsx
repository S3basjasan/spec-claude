import { useState, type FormEvent } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { MAX_MOTIVO, type Inscripcion } from '../api'
import { useRetirarInscripcion } from '../hooks/useMutacionesRutas'
import { rutasText } from '../text'
import { validarMotivo } from '../validation'

const t = rutasText.retirar

interface RetirarModalProps {
  inscripcion: Inscripcion
  rutaId: number
  onCerrar: () => void
  onRetirado: (mensaje: string) => void
}

/** Retira una inscripción: pide confirmación y exige el motivo, que queda en el historial. */
export function RetirarModal({ inscripcion, rutaId, onCerrar, onRetirado }: RetirarModalProps) {
  const retirar = useRetirarInscripcion(rutaId)
  const [motivo, setMotivo] = useState('')
  const [error, setError] = useState<string | undefined>()
  const [errorGeneral, setErrorGeneral] = useState<string | undefined>()
  const nombre = inscripcion.colaborador.nombre

  const enviar = (evento: FormEvent) => {
    evento.preventDefault()
    if (retirar.isPending) return
    const invalido = validarMotivo(motivo)
    setError(invalido)
    setErrorGeneral(undefined)
    if (invalido) return

    retirar.mutate(
      { id: inscripcion.id, motivo: motivo.trim() },
      {
        onSuccess: () => onRetirado(t.exito(nombre)),
        onError: (e) => {
          const api = toApiError(e)
          const delServidor = api.fieldErrors.motivo?.[0]
          setError(delServidor)
          setErrorGeneral(delServidor ? undefined : (api.message ?? t.errorGuardar))
        },
      },
    )
  }

  return (
    <Modal open title={t.title} onClose={onCerrar} description={t.description(nombre)} closeLabel={rutasText.cerrar} busy={retirar.isPending}>
      <form onSubmit={enviar} noValidate className="flex flex-col gap-4">
        {errorGeneral && <Alert>{errorGeneral}</Alert>}
        <TextareaField id="retirar-motivo" label={t.motivo} rows={3} value={motivo} onChange={(e) => setMotivo(e.target.value)} error={error} currentLength={motivo.length} maxLength={MAX_MOTIVO} />
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
          <Button variant="secondary" className="border-transparent" onClick={onCerrar} disabled={retirar.isPending}>
            {rutasText.cancelar}
          </Button>
          <Button type="submit" variant="danger" loading={retirar.isPending}>
            {t.confirmar}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
