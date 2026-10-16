import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import type { Formacion } from '../api'
import { useGuardarFormacion } from '../hooks/useGuardarDesarrollo'
import { desarrolloText } from '../text'
import {
  datosDeFormacion,
  formacionSchema,
  LIMITES,
  problemaDeGuardado,
  valoresDeFormacion,
  valoresVaciosFormacion,
  type FormacionFormValues,
  type ProblemaDeGuardado,
} from '../validation'
import { AvisosDeGuardado } from './AvisosDeGuardado'

const text = desarrolloText.formacion
const FORM_ID = 'formulario-formacion'
const CAMPOS = ['nivel', 'titulo_programa', 'institucion', 'estado', 'fecha_inicio', 'fecha_finalizacion', 'observaciones'] as const

interface FormularioFormacionProps {
  colaboradorId: number
  /** Fecha de hoy del tenant (la envía el servidor con el desarrollo). */
  hoy: string
  /** Fila vigente a corregir; sin ella, alta. La versión se lee en cada envío. */
  fila?: Formacion
  onCerrar: () => void
  onGuardado: () => void
  /** Vuelve a consultar el desarrollo tras un conflicto (conserva lo escrito en el formulario). */
  onActualizar: () => void
}

export function FormularioFormacion({ colaboradorId, hoy, fila, onCerrar, onGuardado, onActualizar }: FormularioFormacionProps) {
  const guardar = useGuardarFormacion(colaboradorId)
  const esquema = useMemo(() => formacionSchema(hoy), [hoy])
  const {
    register,
    handleSubmit,
    control,
    formState: { errors },
  } = useForm<FormacionFormValues>({
    resolver: zodResolver(esquema) as never,
    defaultValues: fila ? valoresDeFormacion(fila) : valoresVaciosFormacion(),
  })
  const [problema, setProblema] = useState<ProblemaDeGuardado>({ campos: {}, aviso: null })
  const enviando = useRef(false)
  const valores = useWatch({ control })

  const error = (campo: (typeof CAMPOS)[number]) => errors[campo]?.message ?? problema.campos[campo]
  const hayErrores = Object.keys(errors).length > 0 || Object.keys(problema.campos).length > 0
  const largo = (campo: keyof FormacionFormValues) => String(valores[campo] ?? '').length

  const recargar = () => {
    setProblema({ campos: {}, aviso: null })
    onActualizar()
  }

  const enviar = (formulario: FormacionFormValues) => {
    if (enviando.current) return
    enviando.current = true
    setProblema({ campos: {}, aviso: null })
    guardar.mutate(
      { datos: datosDeFormacion(formulario), fila: fila ? { id: fila.id, version: fila.version } : undefined },
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
          <TextField id="formacion-nivel" label={text.fields.level} error={error('nivel')} hint={`${largo('nivel')}/${LIMITES.nivel}`} {...register('nivel')} />
          <SelectField
            id="formacion-estado"
            label={text.fields.status}
            error={error('estado')}
            options={[
              { value: '', label: text.fields.statusPlaceholder },
              { value: 'EnCurso', label: desarrolloText.estadosFormacion.EnCurso },
              { value: 'Completada', label: desarrolloText.estadosFormacion.Completada },
              { value: 'Incompleta', label: desarrolloText.estadosFormacion.Incompleta },
            ]}
            {...register('estado')}
          />
          <div className="sm:col-span-2">
            <TextField
              id="formacion-titulo"
              label={text.fields.title}
              error={error('titulo_programa')}
              hint={`${largo('titulo_programa')}/${LIMITES.tituloPrograma}`}
              {...register('titulo_programa')}
            />
          </div>
          <div className="sm:col-span-2">
            <TextField
              id="formacion-institucion"
              label={text.fields.institution}
              error={error('institucion')}
              hint={`${largo('institucion')}/${LIMITES.institucion}`}
              {...register('institucion')}
            />
          </div>
          <TextField id="formacion-inicio" type="date" label={text.fields.start} error={error('fecha_inicio')} {...register('fecha_inicio')} />
          <TextField id="formacion-fin" type="date" label={text.fields.end} error={error('fecha_finalizacion')} {...register('fecha_finalizacion')} />
          <div className="sm:col-span-2">
            <TextareaField
              id="formacion-observaciones"
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
