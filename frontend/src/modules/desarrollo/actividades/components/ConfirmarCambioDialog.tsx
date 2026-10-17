import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { actividadesText } from '../text'
import type { CambioSobreRegistro } from '../registros'

const t = actividadesText.registro.confirmar

interface ConfirmarCambioDialogProps {
  cambios: CambioSobreRegistro[]
  guardando: boolean
  error?: string
  onConfirmar: () => void
  onCancelar: () => void
}

/** Confirmación accesible al guardar cambios sobre participantes que ya tenían un registro. */
export function ConfirmarCambioDialog({ cambios, guardando, error, onConfirmar, onCancelar }: ConfirmarCambioDialogProps) {
  return (
    <Modal
      open
      title={t.title}
      onClose={onCancelar}
      busy={guardando}
      description={t.description}
      footer={
        <>
          <Button variant="secondary" onClick={onCancelar} disabled={guardando}>
            {t.cancelar}
          </Button>
          <Button loading={guardando} onClick={onConfirmar}>
            {t.confirmar}
          </Button>
        </>
      }
    >
      <ul className="m-0 flex list-disc flex-col gap-1 pl-5 text-[15px]">
        {cambios.map((c) => (
          <li key={c.colaborador_id}>{actividadesText.registro.confirmar.cambio(c.nombre, c.antes, c.despues)}</li>
        ))}
      </ul>
      {error && (
        <p role="alert" className="mt-3 text-sm text-danger">
          {error}
        </p>
      )}
    </Modal>
  )
}
