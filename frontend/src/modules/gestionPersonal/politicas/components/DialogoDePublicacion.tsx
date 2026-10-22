import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { TextField } from '../../../../shared/ui/TextField'
import type { InicioDePublicacion, TipoPolitica, Version } from '../api'
import { useClaveDeOperacion } from '../hooks/useClaveDeOperacion'
import { usePrevisualizarPublicacion, usePublicarVersion } from '../hooks/useGuardarPolitica'
import { politicasText } from '../text'
import {
  formatearInstanteLocal,
  LIMITES,
  problemaDeGuardado,
  publicacionSchema,
  type AvisoDeGuardado,
  type PublicacionFormValues,
} from '../validation'
import { ListaDeDiferencias } from './ComparacionDeVersiones'
import { CondicionesFijas } from './CondicionesFijas'
import { ResumenDeParametros } from './ResumenDeParametros'

const text = politicasText.publish
const FORM_ID = 'formulario-publicacion'

const AVISOS: Record<Exclude<AvisoDeGuardado, null | 'iniciada' | 'noRetirable'>, string> = {
  conflicto: text.conflict,
  programada: text.scheduledConflict,
  noExiste: text.notFound,
  deshabilitada: text.writeDisabled,
  red: text.network,
  inesperado: text.unexpected,
}

interface DialogoDePublicacionProps {
  tipo: TipoPolitica
  /** Borrador guardado que se publica (su `version` es el control de edición). */
  borrador: Version
  onCerrar: () => void
  onPublicada: (version: Version) => void
}

function inicioDe(modo: PublicacionFormValues['modo'], fecha: string): InicioDePublicacion | null {
  if (modo === 'inmediato') return { modo: 'inmediato' }
  return fecha.trim() === '' ? null : { modo: 'programado', fecha_hora_local: fecha }
}

/**
 * Revisión y publicación: muestra las condiciones resultantes, las diferencias con la vigente, el inicio y el efecto
 * sobre las solicitudes, y exige motivo, constancia de revisión y confirmación explícita.
 */
export function DialogoDePublicacion({ tipo, borrador, onCerrar, onPublicada }: DialogoDePublicacionProps) {
  const previsualizar = usePrevisualizarPublicacion(tipo)
  const publicar = usePublicarVersion(tipo)
  const {
    register,
    handleSubmit,
    control,
    setError,
    formState: { errors },
  } = useForm<PublicacionFormValues>({
    resolver: zodResolver(publicacionSchema),
    defaultValues: { motivo: '', revisor: '', observaciones: '', modo: 'inmediato', fecha_hora_local: '' },
  })
  const valores = useWatch({ control })
  const [aviso, setAviso] = useState<AvisoDeGuardado>(null)
  const claveDe = useClaveDeOperacion()
  const { mutate: pedirResumen } = previsualizar

  const modo = valores.modo ?? 'inmediato'
  const fecha = valores.fecha_hora_local ?? ''

  // El resumen se recalcula al abrir y cada vez que cambia el inicio elegido; no escribe nada.
  useEffect(() => {
    pedirResumen({ id: borrador.id, inicio: inicioDe(modo, fecha) })
  }, [borrador.id, modo, fecha, pedirResumen])

  const resumen = previsualizar.data
  const erroresDelResumen = Object.entries(resumen?.errores ?? {})
  const bloqueado = publicar.isPending || previsualizar.isPending || previsualizar.isError || erroresDelResumen.length > 0

  const enviar = (formulario: PublicacionFormValues) => {
    if (publicar.isPending) return
    const inicio = inicioDe(formulario.modo, formulario.fecha_hora_local)
    if (inicio === null) return
    setAviso(null)

    const datos = {
      version: borrador.version,
      motivo: formulario.motivo.trim(),
      revision: { revisor: formulario.revisor.trim(), observaciones: formulario.observaciones.trim() },
      inicio,
    }
    // Un reintento del mismo contenido reutiliza la clave: si la primera llegó al servidor, devuelve el resultado original.
    const clave = claveDe(JSON.stringify(datos))

    publicar.mutate(
      { id: borrador.id, datos, clave },
      {
        onSuccess: onPublicada,
        onError: (error) => {
          const problema = problemaDeGuardado(error)
          for (const [campo, mensaje] of Object.entries(problema.campos)) {
            const destino = campo === 'motivo' ? 'motivo' : campo === 'revision.revisor' ? 'revisor' : campo === 'revision.observaciones' ? 'observaciones' : campo === 'inicio.fecha_hora_local' ? 'fecha_hora_local' : null
            if (destino) setError(destino, { message: mensaje })
          }
          setAviso(problema.aviso)
        },
      },
    )
  }

  return (
    <Modal
      open
      size="lg"
      title={text.title}
      onClose={onCerrar}
      busy={publicar.isPending}
      description={text.intro}
      footer={
        <>
          <Button variant="secondary" onClick={onCerrar} disabled={publicar.isPending}>
            {text.cancel}
          </Button>
          <Button type="submit" form={FORM_ID} loading={publicar.isPending} disabled={bloqueado}>
            {publicar.isPending ? text.publishing : text.confirm}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-5">
        {previsualizar.isPending && (
          <div role="status" aria-label={text.loading}>
            <Skeleton className="h-24" />
          </div>
        )}
        {previsualizar.isError && <Alert>{text.previewError}</Alert>}

        {resumen && (
          <>
            {erroresDelResumen.length > 0 && (
              <Alert>
                <ul className="list-disc pl-4">
                  {erroresDelResumen.map(([campo, mensajes]) => (
                    <li key={campo}>{mensajes.join(' ')}</li>
                  ))}
                </ul>
              </Alert>
            )}

            <section aria-label={text.conditions} className="flex flex-col gap-2">
              <h3 className="text-sm font-bold">{text.conditions}</h3>
              <ResumenDeParametros parametros={resumen.condiciones.parametros} label={text.conditions} />
            </section>

            <section aria-label={text.differences} className="flex flex-col gap-2">
              <h3 className="text-sm font-bold">{text.differences}</h3>
              <ListaDeDiferencias diferencias={resumen.diferencias} sinVigente={resumen.efecto.sustituye === null} />
            </section>

            <section aria-label={text.effect} className="flex flex-col gap-1 text-[15px]">
              <h3 className="text-sm font-bold">{text.effect}</h3>
              <p>{text.effectNew}</p>
              <p>{text.effectOld}</p>
              {resumen.efecto.sustituye !== null && <p>{text.effectReplaces(resumen.efecto.sustituye)}</p>}
              {resumen.inicio_vigencia_local && <p className="font-semibold">{text.startAt(formatearInstanteLocal(resumen.inicio_vigencia_local))}</p>}
            </section>

            <CondicionesFijas reglas={resumen.condiciones.reglas_fijas} />
          </>
        )}

        <form id={FORM_ID} onSubmit={handleSubmit(enviar)} noValidate className="flex flex-col gap-4">
          <fieldset className="flex flex-col gap-2">
            <legend className="text-sm font-bold">{text.startTitle}</legend>
            <label className="flex items-center gap-2">
              <input type="radio" value="inmediato" {...register('modo')} className="size-5" />
              {text.startNow}
            </label>
            <label className="flex items-center gap-2">
              <input type="radio" value="programado" {...register('modo')} className="size-5" />
              {text.startLater}
            </label>
            {modo === 'programado' && (
              <TextField id="publicacion-inicio" label={text.startLabel} hint={text.startHint} type="datetime-local" {...register('fecha_hora_local')} error={errors.fecha_hora_local?.message} />
            )}
          </fieldset>

          <TextareaField
            id="publicacion-motivo"
            label={text.reasonLabel}
            {...register('motivo')}
            currentLength={(valores.motivo ?? '').length}
            maxLength={LIMITES.motivo}
            error={errors.motivo?.message}
          />
          <TextField id="publicacion-revisor" label={text.reviewerLabel} {...register('revisor')} error={errors.revisor?.message} />
          <TextareaField
            id="publicacion-observaciones"
            label={text.reviewerNotesLabel}
            hint={text.reviewNote}
            {...register('observaciones')}
            currentLength={(valores.observaciones ?? '').length}
            maxLength={LIMITES.observacionesRevision}
            error={errors.observaciones?.message}
          />
        </form>

        {aviso !== null && aviso !== 'iniciada' && aviso !== 'noRetirable' && <Alert>{AVISOS[aviso]}</Alert>}
      </div>
    </Modal>
  )
}
