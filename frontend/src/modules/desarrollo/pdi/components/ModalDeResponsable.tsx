import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField } from '../../../../shared/ui/SelectField'
import type { Referencia } from '../api'
import { pdiText } from '../text'

const t = pdiText.modales.responsable

interface ModalDeResponsableProps {
  open: boolean
  responsables: Referencia[]
  actualId: number
  busy?: boolean
  error?: string | null
  onConfirm: (responsableId: number) => void
  onClose: () => void
}

/** Modal para reasignar el responsable de un plan Borrador o Acordado. */
export function ModalDeResponsable({ open, responsables, actualId, busy = false, error = null, onConfirm, onClose }: ModalDeResponsableProps) {
  const [elegido, setElegido] = useState(String(actualId))

  return (
    <Modal
      open={open}
      title={t.titulo}
      onClose={onClose}
      busy={busy}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {pdiText.modales.cancelar}
          </Button>
          <Button loading={busy} onClick={() => onConfirm(Number(elegido))} disabled={Number(elegido) === actualId}>
            {t.confirmar}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        {error !== null && <Alert>{error}</Alert>}
        <SelectField id="pdi-nuevo-responsable" label={t.etiqueta} options={responsables.map((r) => ({ value: String(r.id), label: r.nombre }))} value={elegido} onChange={(e) => setElegido(e.target.value)} />
      </div>
    </Modal>
  )
}
