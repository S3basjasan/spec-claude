import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField } from '../../../../shared/ui/SelectField'
import { useReasignarAprobador } from '../hooks/useCambiarEstado'
import { useOpcionesPlan } from '../hooks/useOpcionesPlan'
import { planText } from '../text'

const t = planText.aprobador

interface CambiarAprobadorDialogProps {
  open: boolean
  planId: number
  aprobadorActual: number | null
  onClose: () => void
}

/** Reasigna el aprobador de un plan en Revisión (cualquier administrador puede hacerlo). */
export function CambiarAprobadorDialog(props: CambiarAprobadorDialogProps) {
  if (!props.open) return null
  return <Contenido {...props} />
}

function Contenido({ planId, aprobadorActual, onClose }: CambiarAprobadorDialogProps) {
  const opciones = useOpcionesPlan()
  const reasignar = useReasignarAprobador()
  const [seleccion, setSeleccion] = useState<string>(aprobadorActual === null ? '' : String(aprobadorActual))
  const [error, setError] = useState<string | undefined>()

  const confirmar = async () => {
    if (seleccion === '') {
      setError(t.requerido)
      return
    }
    setError(undefined)
    try {
      await reasignar.mutateAsync({ id: planId, aprobadorId: Number(seleccion) })
      onClose()
    } catch (e) {
      const api = toApiError(e)
      setError(api.code === 'aprobador_invalido' ? t.invalido : (api.message ?? planText.error.generico))
    }
  }

  return (
    <Modal
      open
      title={t.title}
      description={t.description}
      onClose={onClose}
      busy={reasignar.isPending}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={reasignar.isPending}>
            {planText.transicion.cancelar}
          </Button>
          <Button loading={reasignar.isPending} onClick={() => void confirmar()}>
            {t.confirmar}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-3">
        <p className="text-[15px] text-muted">{t.description}</p>
        {error && <Alert>{error}</Alert>}
        <SelectField
          id="cambiar-aprobador"
          label={t.label}
          value={seleccion}
          onChange={(e) => setSeleccion(e.target.value)}
          options={[{ value: '', label: t.placeholder }, ...(opciones.data?.aprobadores ?? []).map((a) => ({ value: String(a.id), label: a.nombre }))]}
        />
      </div>
    </Modal>
  )
}
