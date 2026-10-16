import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { TextField } from '../../../../shared/ui/TextField'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import type { Experiencia } from '../api'
import { useGuardarExperiencia } from '../hooks/useGuardarDesarrollo'
import { desarrolloText } from '../text'
import {
  datosDeExperiencia,
  experienciaSchema,
  LIMITES,
  problemaDeGuardado,
  valoresDeExperiencia,
  valoresVaciosExperiencia,
  type ExperienciaFormValues,
  type ProblemaDeGuardado,
} from '../validation'
import { AvisosDeGuardado } from './AvisosDeGuardado'

const text = desarrolloText.experiencia
const FORM_ID = 'formulario-experiencia'
const CAMPOS = ['organizacion', 'cargo', 'fecha_inicio', 'fecha_finalizacion', 'funciones', 'observaciones'] as const

interface FormularioExperienciaProps {
  colaboradorId: number
  hoy: string
  fila?: Experiencia
  onCerrar: () => void
  onGuardado: () => void
  onActualizar: () => void
}

export function FormularioExperiencia({ colaboradorId, hoy, fila, onCerrar, onGuardado, onActualizar }: FormularioExperienciaProps) {
  const guardar = useGuardarExperiencia(colaboradorId)
  const esquema = useMemo(() => experienciaSchema(hoy), [hoy])
  const {
    register,
    handleSubmit,
    control,
    formState: { errors },
  } = useForm<ExperienciaFormValues>({
    resolver: zodResolver(esquema) as never,
    defaultValues: fila ? valoresDeExperiencia(fila) : valoresVaciosExperiencia(),
  })
  const [problema, setProblema] = useState<ProblemaDeGuardado>({ campos: {}, aviso: null })
  const enviando = useRef(false)
  const valores = useWatch({ control })

  const error = (campo: (typeof CAMPOS)[number]) => errors[campo]?.message ?? problema.campos[campo]
  const hayErrores = Object.keys(errors).length > 0 || Object.keys(problema.campos).length > 0
  const largo = (campo: keyof ExperienciaFormValues) => String(valores[campo] ?? '').length

  const recargar = () => {
    setProblema({ campos: {}, aviso: null })
    onActualizar()
  }

  const enviar = (formulario: ExperienciaFormValues) => {
    if (enviando.current) return
    enviando.current = true
    setProblema({ campos: {}, aviso: null })
    guardar.mutate(
      { datos: datosDeExperiencia(formulario), fila: fila ? { id: fila.id, version: fila.version } : undefined },
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
      size="lg"
      title={fila ? text.editTitle : text.createTitle}
      description={text.subtitle}
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

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <TextField
            id="experiencia-organizacion"
            label={text.fields.organization}
            error={error('organizacion')}
            hint={`${largo('organizacion')}/${LIMITES.organizacion}`}
            {...register('organizacion')}
          />
          <TextField id="experiencia-cargo" label={text.fields.position} error={error('cargo')} hint={`${largo('cargo')}/${LIMITES.cargo}`} {...register('cargo')} />
          <TextField id="experiencia-inicio" type="date" label={text.fields.start} error={error('fecha_inicio')} {...register('fecha_inicio')} />
          <TextField id="experiencia-fin" type="date" label={text.fields.end} error={error('fecha_finalizacion')} {...register('fecha_finalizacion')} />
          <div className="sm:col-span-2">
            <TextareaField
              id="experiencia-funciones"
              label={text.fields.functions}
              error={error('funciones')}
              currentLength={largo('funciones')}
              maxLength={LIMITES.funciones}
              {...register('funciones')}
            />
          </div>
          <div className="sm:col-span-2">
            <TextareaField
              id="experiencia-observaciones"
              label={text.fields.notes}
              error={error('observaciones')}
              currentLength={largo('observaciones')}
              maxLength={LIMITES.observaciones}
              {...register('observaciones')}
            />
          </div>
        </div>
      </form>
    </Modal>
  )
}
