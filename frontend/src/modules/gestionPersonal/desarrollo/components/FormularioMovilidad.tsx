import { zodResolver } from '@hookform/resolvers/zod'
import { useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import type { EstadoMovilidad, Movilidad } from '../api'
import { useGuardarMovilidad } from '../hooks/useGuardarDesarrollo'
import { desarrolloText } from '../text'
import {
  datosDeMovilidad,
  LIMITES,
  movilidadSchema,
  problemaDeGuardado,
  valoresDeMovilidad,
  type MovilidadFormValues,
  type ProblemaDeGuardado,
} from '../validation'
import { AvisosDeGuardado } from './AvisosDeGuardado'

const text = desarrolloText.movilidad
const FORM_ID = 'formulario-movilidad'
const CAMPOS = ['estado', 'observaciones'] as const
const ESTADOS: EstadoMovilidad[] = ['Disponible', 'NoDisponible', 'NoInformada']

interface FormularioMovilidadProps {
  colaboradorId: number
  /** Bloque vigente: su `version` se lee en cada envío. */
  movilidad: Movilidad
  onCerrar: () => void
  onGuardado: () => void
  onActualizar: () => void
}

export function FormularioMovilidad({ colaboradorId, movilidad, onCerrar, onGuardado, onActualizar }: FormularioMovilidadProps) {
  const guardar = useGuardarMovilidad(colaboradorId)
  const {
    register,
    handleSubmit,
    control,
    formState: { errors },
  } = useForm<MovilidadFormValues>({
    resolver: zodResolver(movilidadSchema),
    defaultValues: valoresDeMovilidad(movilidad),
  })
  const [problema, setProblema] = useState<ProblemaDeGuardado>({ campos: {}, aviso: null })
  const enviando = useRef(false)
  const valores = useWatch({ control })

  const error = (campo: (typeof CAMPOS)[number]) => errors[campo]?.message ?? problema.campos[campo]
  const hayErrores = Object.keys(errors).length > 0 || Object.keys(problema.campos).length > 0

  const recargar = () => {
    setProblema({ campos: {}, aviso: null })
    onActualizar()
  }

  const enviar = (formulario: MovilidadFormValues) => {
    if (enviando.current) return
    enviando.current = true
    setProblema({ campos: {}, aviso: null })
    guardar.mutate(
      { datos: datosDeMovilidad(formulario), version: movilidad.version },
      {
        onSuccess: onGuardado,
        onError: (e) => setProblema(problemaDeGuardado(e, CAMPOS)),
        onSettled: () => {
          enviando.current = false
        },
      },
    )
  }

  return (
    <Modal
      open
      title={text.dialogTitle}
      description={text.note}
      busy={guardar.isPending}
      closeLabel={desarrolloText.dialog.close}
      onClose={onCerrar}
      footer={
        <>
          <Button variant="secondary" disabled={guardar.isPending} onClick={onCerrar}>
            {desarrolloText.dialog.cancel}
          </Button>
          <Button type="submit" form={FORM_ID} loading={guardar.isPending}>
            {text.save}
          </Button>
        </>
      }
    >
      <form id={FORM_ID} noValidate onSubmit={(evento) => void handleSubmit(enviar)(evento)} className="flex flex-col gap-4">
        {hayErrores && <Alert>{desarrolloText.dialog.errorsAlert}</Alert>}
        <AvisosDeGuardado problema={problema.aviso} onActualizar={recargar} />

        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1 text-sm font-semibold">{text.fields.status}</legend>
          <div className="flex flex-wrap gap-2">
            {ESTADOS.map((estado) => (
              <label key={estado} className="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-input-border px-3.5 text-[15px]">
                <input type="radio" value={estado} {...register('estado')} />
                {desarrolloText.estadosMovilidad[estado]}
              </label>
            ))}
          </div>
          {error('estado') && <p className="text-[13px] text-danger">{error('estado')}</p>}
        </fieldset>

        <TextareaField
          id="movilidad-observaciones"
          label={text.fields.notes}
          error={error('observaciones')}
          currentLength={String(valores.observaciones ?? '').length}
          maxLength={LIMITES.observaciones}
          {...register('observaciones')}
        />
      </form>
    </Modal>
  )
}
