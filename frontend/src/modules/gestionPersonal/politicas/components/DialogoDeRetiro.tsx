import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import type { TipoPolitica, Version } from '../api'
import { useClaveDeOperacion } from '../hooks/useClaveDeOperacion'
import { useRetirarVersion } from '../hooks/useGuardarPolitica'
import { politicasText } from '../text'
import { LIMITES, problemaDeGuardado, retiroSchema, type AvisoDeGuardado, type RetiroFormValues } from '../validation'

const text = politicasText.retire
const FORM_ID = 'formulario-retiro'

const AVISOS: Record<Exclude<AvisoDeGuardado, null | 'conflicto' | 'programada'>, string> = {
  iniciada: text.started,
  noRetirable: text.notRetirable,
  noExiste: text.notFound,
  deshabilitada: text.writeDisabled,
  red: text.network,
  inesperado: text.unexpected,
}

interface DialogoDeRetiroProps {
  tipo: TipoPolitica
  version: Version
  onCerrar: () => void
  onRetirada: (version: Version) => void
}

/** Retiro de una versión programada: pide justificación y advierte que la versión anterior seguirá aplicándose. */
export function DialogoDeRetiro({ tipo, version, onCerrar, onRetirada }: DialogoDeRetiroProps) {
  const retirar = useRetirarVersion(tipo)
  const {
    register,
    handleSubmit,
    control,
    setError,
    formState: { errors },
  } = useForm<RetiroFormValues>({ resolver: zodResolver(retiroSchema), defaultValues: { justificacion: '' } })
  const valores = useWatch({ control })
  const [aviso, setAviso] = useState<AvisoDeGuardado>(null)
  const claveDe = useClaveDeOperacion()

  const enviar = (formulario: RetiroFormValues) => {
    if (retirar.isPending) return
    setAviso(null)
    const justificacion = formulario.justificacion.trim()

    retirar.mutate(
      { id: version.id, justificacion, clave: claveDe(justificacion) },
      {
        onSuccess: onRetirada,
        onError: (error) => {
          const problema = problemaDeGuardado(error)
          if (problema.campos.justificacion) setError('justificacion', { message: problema.campos.justificacion })
          setAviso(problema.aviso)
        },
      },
    )
  }

  return (
    <Modal
      open
      title={text.title}
      onClose={onCerrar}
      busy={retirar.isPending}
      description={text.description}
      footer={
        <>
          <Button variant="secondary" onClick={onCerrar} disabled={retirar.isPending}>
            {text.cancel}
          </Button>
          <Button type="submit" form={FORM_ID} variant="danger" loading={retirar.isPending}>
            {retirar.isPending ? text.retiring : text.confirm}
          </Button>
        </>
      }
    >
      <form id={FORM_ID} onSubmit={handleSubmit(enviar)} noValidate className="flex flex-col gap-4">
        <p className="text-[15px]">{text.description}</p>
        <Alert tone="neutral">{text.warning}</Alert>
        <TextareaField
          id="retiro-justificacion"
          label={text.reasonLabel}
          {...register('justificacion')}
          currentLength={(valores.justificacion ?? '').length}
          maxLength={LIMITES.justificacionRetiro}
          error={errors.justificacion?.message}
        />
        {aviso !== null && aviso !== 'conflicto' && aviso !== 'programada' && <Alert>{AVISOS[aviso]}</Alert>}
      </form>
    </Modal>
  )
}
