import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { MOTIVOS_DE_NO_REALIZADA, type MotivoDeNoRealizada, type TareaDeSalida } from '../api'
import { useRespaldosDeSalida } from '../hooks/useOffboarding'
import { offboardingText } from '../text'
import { esquemaDeCancelacion, RAZON_MAXIMA } from '../validation'
import { SelectorDeRespaldos } from './SelectorDeRespaldos'

interface Comun {
  open: boolean
  busy: boolean
  /** Error de la última confirmación; el diálogo sigue abierto con lo escrito. */
  error?: string
  onCancel: () => void
}

/** Confirmación simple (aplicar la salida o confirmar el cierre). */
export function DialogoDeConfirmacion({ open, busy, error, onCancel, titulo, descripcion, accion, onConfirm }: Comun & { titulo: string; descripcion: string; accion: string; onConfirm: () => void }) {
  return (
    <Modal
      open={open}
      title={titulo}
      onClose={onCancel}
      busy={busy}
      description={descripcion}
      footer={
        <>
          <Button variant="secondary" onClick={onCancel} disabled={busy}>
            {offboardingText.cancel}
          </Button>
          <Button onClick={onConfirm} loading={busy}>
            {accion}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        {error && <Alert>{error}</Alert>}
      </div>
    </Modal>
  )
}

/** Cancelación: la razón es obligatoria y queda en el historial. */
export function DialogoDeCancelacion({ open, busy, error, onCancel, onConfirm }: Comun & { onConfirm: (razon: string) => void }) {
  const text = offboardingText.file
  const [razon, setRazon] = useState('')
  const [errorDeCampo, setErrorDeCampo] = useState<string>()

  const confirmar = () => {
    const resultado = esquemaDeCancelacion.safeParse({ razon })
    if (!resultado.success) {
      setErrorDeCampo(resultado.error.issues[0]?.message)
      return
    }
    setErrorDeCampo(undefined)
    onConfirm(razon.trim())
  }

  return (
    <Modal
      open={open}
      title={text.cancelTitle}
      onClose={onCancel}
      busy={busy}
      description={text.cancelDescription}
      footer={
        <>
          <Button variant="secondary" onClick={onCancel} disabled={busy}>
            {text.keepIt}
          </Button>
          <Button variant="danger" onClick={confirmar} loading={busy}>
            {text.cancelConfirm}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        {error && <Alert>{error}</Alert>}
        <TextareaField id="cancelacion-razon" label={text.cancelReason} value={razon} onChange={(e) => setRazon(e.target.value)} maxLength={RAZON_MAXIMA} currentLength={razon.length} error={errorDeCampo} />
      </div>
    </Modal>
  )
}

/** Entrevista «no realizada»: resuelve la tarea sin respuestas, con un motivo del catálogo. */
export function DialogoDeNoRealizada({ open, busy, error, onCancel, onConfirm }: Comun & { onConfirm: (motivo: MotivoDeNoRealizada) => void }) {
  const text = offboardingText.interview
  const [motivo, setMotivo] = useState<MotivoDeNoRealizada>('SinRespuesta')

  return (
    <Modal
      open={open}
      title={text.notDoneTitle}
      onClose={onCancel}
      busy={busy}
      description={text.notDoneDescription}
      footer={
        <>
          <Button variant="secondary" onClick={onCancel} disabled={busy}>
            {offboardingText.cancel}
          </Button>
          <Button onClick={() => onConfirm(motivo)} loading={busy}>
            {text.notDoneConfirm}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        {error && <Alert>{error}</Alert>}
        <SelectField id="no-realizada-motivo" label={text.notDoneReason} value={motivo} onChange={(e) => setMotivo(e.target.value as MotivoDeNoRealizada)} options={MOTIVOS_DE_NO_REALIZADA.map((m) => ({ value: m, label: offboardingText.noRealizada[m] }))} />
      </div>
    </Modal>
  )
}

/** Cumplimiento de una tarea: si exige evidencia, hay que elegir al menos un respaldo disponible. */
export function DialogoDeTarea({ open, busy, error, onCancel, onConfirm, tarea, colaboradorId }: Comun & { tarea: TareaDeSalida; colaboradorId: number; onConfirm: (documentoIds: number[]) => void }) {
  const text = offboardingText.checklist
  const [elegidos, setElegidos] = useState<number[]>([])
  const [errorDeCampo, setErrorDeCampo] = useState<string>()
  const respaldos = useRespaldosDeSalida(open ? colaboradorId : null)

  const confirmar = () => {
    if (tarea.exige_evidencia && elegidos.length === 0) {
      setErrorDeCampo(text.evidenceRequired)
      return
    }
    setErrorDeCampo(undefined)
    onConfirm(elegidos)
  }

  return (
    <Modal
      open={open}
      title={text.completeTitle(tarea.nombre)}
      onClose={onCancel}
      busy={busy}
      description={text.completeDescription}
      footer={
        <>
          <Button variant="secondary" onClick={onCancel} disabled={busy}>
            {offboardingText.cancel}
          </Button>
          <Button onClick={confirmar} loading={busy}>
            {text.completeConfirm}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        {error && <Alert>{error}</Alert>}
        <SelectorDeRespaldos
          titulo={offboardingText.formalize.supports}
          ayuda={tarea.exige_evidencia ? text.evidenceHint : offboardingText.formalize.supportsHint}
          vacio={offboardingText.formalize.supportsNone}
          errorDeCarga={offboardingText.formalize.supportsError}
          respaldos={respaldos.data}
          cargando={respaldos.isPending}
          fallo={respaldos.isError}
          elegidos={elegidos}
          onChange={setElegidos}
          error={errorDeCampo}
        />
      </div>
    </Modal>
  )
}
