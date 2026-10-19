import { useState, type FormEvent } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { Modal } from '../../../../shared/ui/Modal'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { MAX_COMENTARIO, type EncuestaPendiente } from '../api'
import { useResponderEncuesta } from '../hooks/useMutacionesReportes'
import { reportesText } from '../text'
import { tieneErrores, validarEncuesta, type CampoEncuesta, type ErroresDe } from '../validation'
import { EscalaDe5 } from './EscalaDe5'

const t = reportesText.formularioEncuesta

interface FormularioEncuestaProps {
  pendiente: EncuestaPendiente
  onCerrar: () => void
  onEnviada: () => void
}

/** Encuesta de satisfacción (una pregunta de 1 a 5 y comentario opcional). Se envía una sola vez: pide confirmación antes. */
export function FormularioEncuesta({ pendiente, onCerrar, onEnviada }: FormularioEncuestaProps) {
  const responder = useResponderEncuesta()
  const [calificacion, setCalificacion] = useState<number | null>(null)
  const [comentario, setComentario] = useState('')
  const [errores, setErrores] = useState<ErroresDe<CampoEncuesta>>({})
  const [confirmando, setConfirmando] = useState(false)
  const [errorGeneral, setErrorGeneral] = useState<string | undefined>()

  const revisar = (evento: FormEvent) => {
    evento.preventDefault()
    if (responder.isPending) return
    const encontrados = validarEncuesta({ calificacion, comentario })
    setErrores(encontrados)
    setErrorGeneral(undefined)
    if (!tieneErrores(encontrados)) setConfirmando(true)
  }

  const enviar = () => {
    if (calificacion === null) return
    responder.mutate(
      { actividad_id: pendiente.actividad_id, calificacion, comentario: comentario.trim() === '' ? undefined : comentario.trim() },
      {
        onSuccess: onEnviada,
        onError: (error) => {
          const api = toApiError(error)
          setConfirmando(false)
          setErrorGeneral(reportesText.mensajeDeError(api.code, t.errorGeneral))
        },
      },
    )
  }

  return (
    <>
      <Modal
        open={!confirmando}
        title={t.titulo(pendiente.nombre)}
        onClose={onCerrar}
        busy={responder.isPending}
        description={t.descripcion}
        closeLabel={t.cerrar}
        footer={
          <>
            <Button variant="secondary" onClick={onCerrar} disabled={responder.isPending}>
              {t.cancelar}
            </Button>
            <Button type="submit" form="form-encuesta">
              {t.enviar}
            </Button>
          </>
        }
      >
        <form id="form-encuesta" noValidate onSubmit={revisar} className="flex flex-col gap-4">
          {errorGeneral && <Alert>{errorGeneral}</Alert>}
          <EscalaDe5
            id="encuesta-calificacion"
            legend={t.calificacion}
            hint={t.calificacionHint}
            value={calificacion}
            error={errores.calificacion}
            onChange={(n) => {
              setCalificacion(n)
              setErrores((e) => ({ ...e, calificacion: undefined }))
            }}
          />
          <TextareaField
            id="encuesta-comentario"
            label={t.comentario}
            hint={t.comentarioHint}
            rows={3}
            maxLength={MAX_COMENTARIO}
            currentLength={comentario.length}
            value={comentario}
            error={errores.comentario}
            onChange={(e) => {
              setComentario(e.target.value)
              setErrores((er) => ({ ...er, comentario: undefined }))
            }}
          />
        </form>
      </Modal>
      <ConfirmDialog
        open={confirmando}
        tone="primary"
        title={t.confirmar.titulo}
        description={t.confirmar.descripcion}
        confirmLabel={t.confirmar.confirmar}
        cancelLabel={t.confirmar.volver}
        busy={responder.isPending}
        onConfirm={enviar}
        onCancel={() => setConfirmando(false)}
      />
    </>
  )
}
