import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { MAX_COMENTARIO } from '../api'
import { pdiText } from '../text'

interface ModalDeMotivoProps {
  open: boolean
  titulo: string
  descripcion: string
  etiqueta: string
  /** Mensaje cuando se intenta confirmar sin texto. */
  obligatorio: string
  confirmar: string
  busy?: boolean
  error?: string | null
  onConfirm: (texto: string) => void
  onClose: () => void
}

/** Modal con un texto obligatorio (motivo de devolución a Borrador o comentario del colaborador). */
export function ModalDeMotivo({ open, titulo, descripcion, etiqueta, obligatorio, confirmar, busy = false, error = null, onConfirm, onClose }: ModalDeMotivoProps) {
  const [texto, setTexto] = useState('')
  const [falta, setFalta] = useState(false)

  const cerrar = () => {
    setTexto('')
    setFalta(false)
    onClose()
  }

  const enviar = () => {
    if (texto.trim() === '') {
      setFalta(true)
      return
    }
    onConfirm(texto.trim())
  }

  return (
    <Modal
      open={open}
      title={titulo}
      description={descripcion}
      onClose={cerrar}
      busy={busy}
      footer={
        <>
          <Button variant="secondary" onClick={cerrar} disabled={busy}>
            {pdiText.modales.cancelar}
          </Button>
          <Button loading={busy} onClick={enviar}>
            {confirmar}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <p className="m-0 text-muted">{descripcion}</p>
        {error !== null && <Alert>{error}</Alert>}
        <TextareaField
          id="pdi-motivo"
          label={etiqueta}
          value={texto}
          maxLength={MAX_COMENTARIO}
          currentLength={texto.length}
          onChange={(e) => {
            setTexto(e.target.value)
            setFalta(false)
          }}
          error={falta ? obligatorio : undefined}
        />
      </div>
    </Modal>
  )
}
