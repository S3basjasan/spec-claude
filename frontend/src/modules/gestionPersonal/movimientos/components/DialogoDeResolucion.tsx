import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import type { CruceDePeriodo } from '../api'
import { movimientosText } from '../text'
import { esquemaDeResolucion, esquemaDeRevisionDeAusencia, MOTIVO_MAXIMO, OBSERVACION_MAXIMA } from '../validation'

const text = movimientosText.resolution

export type ModoDeResolucion = 'aprobar' | 'rechazar' | 'retirar' | 'revisar-ausencia' | 'aplicar'

export interface ValoresDeResolucion {
  justificacion: string
  reconoceCruce: boolean
  resultado: 'Revisada' | 'Justificada'
  observacion: string
}

interface DialogoDeResolucionProps {
  modo: ModoDeResolucion
  open: boolean
  cruces: CruceDePeriodo[]
  busy: boolean
  /** Error de la última confirmación; el diálogo sigue abierto con lo escrito. */
  error?: string
  onConfirm: (valores: ValoresDeResolucion) => void
  onCancel: () => void
}

const TITULOS: Record<ModoDeResolucion, string> = {
  aprobar: text.approveTitle,
  rechazar: text.rejectTitle,
  retirar: text.withdrawTitle,
  'revisar-ausencia': text.reviewAbsenceTitle,
  aplicar: text.applyTitle,
}

const DESCRIPCIONES: Record<ModoDeResolucion, string> = {
  aprobar: text.approveDescription,
  rechazar: text.rejectDescription,
  retirar: text.withdrawDescription,
  'revisar-ausencia': '',
  aplicar: text.applyDescription,
}

const CONFIRMAR: Record<ModoDeResolucion, string> = {
  aprobar: text.approveConfirm,
  rechazar: text.rejectConfirm,
  retirar: text.withdrawConfirm,
  'revisar-ausencia': text.reviewConfirm,
  aplicar: text.applyConfirm,
}

/**
 * Confirmación accesible de una resolución: razón obligatoria al aprobar o rechazar, observación al revisar una
 * ausencia, reconocimiento del cruce al aprobar y confirmación simple al retirar o aplicar. Conserva lo escrito
 * mientras hay un error o un conflicto.
 */
export function DialogoDeResolucion({ modo, open, cruces, busy, error, onConfirm, onCancel }: DialogoDeResolucionProps) {
  const [justificacion, setJustificacion] = useState('')
  const [observacion, setObservacion] = useState('')
  const [resultado, setResultado] = useState<'Revisada' | 'Justificada'>('Revisada')
  const [reconoce, setReconoce] = useState(false)
  const [errores, setErrores] = useState<Record<string, string>>({})

  const pideRazon = modo === 'aprobar' || modo === 'rechazar'
  const exigeCruce = modo === 'aprobar' && cruces.length > 0

  const confirmar = () => {
    const nuevos: Record<string, string> = {}
    if (pideRazon) {
      const r = esquemaDeResolucion.safeParse({ justificacion })
      if (!r.success) nuevos.justificacion = r.error.issues[0]?.message ?? text.reasonHint
    }
    if (modo === 'revisar-ausencia') {
      const r = esquemaDeRevisionDeAusencia.safeParse({ resultado, observacion })
      if (!r.success) nuevos.observacion = r.error.issues[0]?.message ?? ''
    }
    if (exigeCruce && !reconoce) nuevos.reconoce = text.acknowledgeRequired
    setErrores(nuevos)
    if (Object.keys(nuevos).length > 0) return
    onConfirm({ justificacion: justificacion.trim(), reconoceCruce: reconoce, resultado, observacion: observacion.trim() })
  }

  return (
    <Modal
      open={open}
      title={TITULOS[modo]}
      onClose={onCancel}
      busy={busy}
      description={DESCRIPCIONES[modo] || undefined}
      footer={
        <>
          <Button variant="secondary" onClick={onCancel} disabled={busy}>
            {text.cancel}
          </Button>
          <Button variant={modo === 'rechazar' || modo === 'retirar' ? 'danger' : 'primary'} onClick={confirmar} loading={busy}>
            {CONFIRMAR[modo]}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        {DESCRIPCIONES[modo] && <p className="text-sm">{DESCRIPCIONES[modo]}</p>}
        {error && <Alert>{error}</Alert>}

        {pideRazon && (
          <TextareaField
            id="resolucion-justificacion"
            label={text.reason}
            hint={text.reasonHint}
            value={justificacion}
            onChange={(e) => setJustificacion(e.target.value)}
            maxLength={MOTIVO_MAXIMO}
            currentLength={justificacion.length}
            error={errores.justificacion}
          />
        )}

        {modo === 'revisar-ausencia' && (
          <>
            <SelectField
              id="resolucion-resultado"
              label={text.outcome}
              value={resultado}
              onChange={(e) => setResultado(e.target.value as 'Revisada' | 'Justificada')}
              options={[
                { value: 'Revisada', label: text.outcomes.Revisada },
                { value: 'Justificada', label: text.outcomes.Justificada },
              ]}
            />
            <TextareaField
              id="resolucion-observacion"
              label={text.observation}
              value={observacion}
              onChange={(e) => setObservacion(e.target.value)}
              maxLength={OBSERVACION_MAXIMA}
              currentLength={observacion.length}
              error={errores.observacion}
            />
          </>
        )}

        {exigeCruce && (
          <div className="flex flex-col gap-1">
            <label className="flex items-start gap-2 text-sm">
              <input
                type="checkbox"
                checked={reconoce}
                onChange={(e) => setReconoce(e.target.checked)}
                aria-invalid={errores.reconoce ? true : undefined}
                className="mt-1 size-4"
              />
              <span>{text.acknowledge}</span>
            </label>
            {errores.reconoce && <p className="text-[13px] text-danger">{errores.reconoce}</p>}
          </div>
        )}
      </div>
    </Modal>
  )
}
