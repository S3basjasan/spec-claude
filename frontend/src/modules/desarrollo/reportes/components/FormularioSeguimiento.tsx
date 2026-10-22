import { useState, type FormEvent } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { Modal } from '../../../../shared/ui/Modal'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { MAX_COMENTARIO, type SeguimientoPendiente } from '../api'
import { useResponderSeguimiento } from '../hooks/useMutacionesReportes'
import { reportesText } from '../text'
import { tieneErrores, validarSeguimiento, type CampoSeguimiento, type ErroresDe } from '../validation'
import { EscalaDe5 } from './EscalaDe5'

const t = reportesText.formularioSeguimiento

interface FormularioSeguimientoProps {
  pendiente: SeguimientoPendiente
  onCerrar: () => void
  onEnviado: () => void
}

/** Seguimiento a 30, 60 o 90 días: aplicación y apoyo del entorno (1 a 5) y comentario opcional. Se envía una sola vez. */
export function FormularioSeguimiento({ pendiente, onCerrar, onEnviado }: FormularioSeguimientoProps) {
  const responder = useResponderSeguimiento()
  const [aplicacion, setAplicacion] = useState<number | null>(null)
  const [apoyo, setApoyo] = useState<number | null>(null)
  const [comentario, setComentario] = useState('')
  const [errores, setErrores] = useState<ErroresDe<CampoSeguimiento>>({})
  const [confirmando, setConfirmando] = useState(false)
  const [errorGeneral, setErrorGeneral] = useState<string | undefined>()

  const revisar = (evento: FormEvent) => {
    evento.preventDefault()
    if (responder.isPending) return
    const encontrados = validarSeguimiento({ aplicacion, apoyo, comentario })
    setErrores(encontrados)
    setErrorGeneral(undefined)
    if (!tieneErrores(encontrados)) setConfirmando(true)
  }

  const enviar = () => {
    if (aplicacion === null || apoyo === null) return
    responder.mutate(
      { actividad_id: pendiente.actividad_id, hito: pendiente.hito, aplicacion, apoyo, comentario: comentario.trim() === '' ? undefined : comentario.trim() },
      {
        onSuccess: onEnviado,
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
        title={t.titulo(pendiente.nombre, pendiente.hito)}
        onClose={onCerrar}
        busy={responder.isPending}
        description={t.descripcion}
        closeLabel={t.cerrar}
        footer={
          <>
            <Button variant="secondary" onClick={onCerrar} disabled={responder.isPending}>
              {t.cancelar}
            </Button>
            <Button type="submit" form="form-seguimiento">
              {t.enviar}
            </Button>
          </>
        }
      >
        <form id="form-seguimiento" noValidate onSubmit={revisar} className="flex flex-col gap-4">
          {errorGeneral && <Alert>{errorGeneral}</Alert>}
          <EscalaDe5
            id="seguimiento-aplicacion"
            legend={t.aplicacion}
            hint={t.aplicacionHint}
            value={aplicacion}
            error={errores.aplicacion}
            onChange={(n) => {
              setAplicacion(n)
              setErrores((e) => ({ ...e, aplicacion: undefined }))
            }}
          />
          <EscalaDe5
            id="seguimiento-apoyo"
            legend={t.apoyo}
            hint={t.apoyoHint}
            value={apoyo}
            error={errores.apoyo}
            onChange={(n) => {
              setApoyo(n)
              setErrores((e) => ({ ...e, apoyo: undefined }))
            }}
          />
          <TextareaField
            id="seguimiento-comentario"
            label={t.comentario}
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
