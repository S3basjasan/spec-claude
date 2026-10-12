import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Controller, useForm, useWatch, type Path } from 'react-hook-form'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { Combobox } from '../../../../shared/ui/Combobox'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextField } from '../../../../shared/ui/TextField'
import { CANTIDAD_PREGUNTAS, type CompetenciaDisponible, type RegistroDetalle } from '../api'
import { CompetenciaResumen } from '../components/CompetenciaResumen'
import { preguntasKey, useCompetenciasDisponibles, usePregunta } from '../hooks/usePreguntas'
import { useActualizarPreguntas, useCrearPreguntas } from '../hooks/useGuardarPreguntas'
import { preguntasText } from '../text'
import { LIMITES, preguntasSchema, type PreguntasFormValues } from '../validation'

const text = preguntasText.form

type Problema = 'red' | 'conflicto' | 'inesperado' | null

const POSICIONES = Array.from({ length: CANTIDAD_PREGUNTAS }, (_, indice) => indice)

interface FormularioProps {
  registro?: RegistroDetalle
  disponibles?: CompetenciaDisponible[]
  onRecargar?: () => void
}

function PreguntasForm({ registro, disponibles = [], onRecargar }: FormularioProps) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const editando = registro !== undefined
  const crear = useCrearPreguntas()
  const actualizar = useActualizarPreguntas(registro?.competencia_id ?? 0, registro?.version ?? 0)
  const guardando = crear.isPending || actualizar.isPending
  const [problema, setProblema] = useState<Problema>(null)

  const {
    register,
    handleSubmit,
    setError,
    setValue,
    control,
    formState: { errors },
  } = useForm<PreguntasFormValues>({
    resolver: zodResolver(preguntasSchema),
    defaultValues: registro
      ? { competencia_id: registro.competencia_id, preguntas: registro.preguntas }
      : { preguntas: POSICIONES.map(() => '') },
  })

  const competenciaId = useWatch({ control, name: 'competencia_id' })
  const valores = useWatch({ control, name: 'preguntas' })
  const elegida = disponibles.find((competencia) => competencia.competencia_id === competenciaId)

  const onSubmit = (values: PreguntasFormValues) => {
    if (guardando) return
    setProblema(null)
    const opciones = {
      onSuccess: () =>
        navigate('/preguntas', { state: { aviso: editando ? preguntasText.list.updated : preguntasText.list.created } }),
      onError: (error: unknown) => {
        const api = toApiError(error)
        if (api.isNetworkError) return setProblema('red')
        if (api.status === 409) return setProblema('conflicto')
        if (api.status === 422) {
          const campos = Object.entries(api.fieldErrors).filter(([campo]) => campo === 'competencia_id' || /^preguntas\.[0-3]$/.test(campo))
          for (const [campo, mensajes] of campos) {
            if (campo === 'competencia_id') {
              // La competencia ya no está disponible: se quita la elección y se refresca la lista.
              setValue('competencia_id', undefined as never)
              void queryClient.invalidateQueries({ queryKey: [...preguntasKey, 'disponibles'] })
            }
            setError(campo as Path<PreguntasFormValues>, { message: mensajes[0] })
          }
          if (campos.length > 0) return
        }
        setProblema('inesperado')
      },
    }
    if (editando) actualizar.mutate(values.preguntas, opciones)
    else crear.mutate({ competenciaId: values.competencia_id, preguntas: values.preguntas }, opciones)
  }

  const hayErrores = Object.keys(errors).length > 0

  return (
    <div className="mx-auto flex max-w-190 flex-col gap-5">
      <Breadcrumb
        label={text.breadcrumbLabel}
        items={[
          { label: text.breadcrumbList, to: '/preguntas' },
          { label: editando ? text.editBreadcrumb : text.newBreadcrumb },
        ]}
      />
      <h1 className="text-[28px] font-bold leading-tight tracking-tight">{editando ? text.editTitle : text.newTitle}</h1>

      <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5 rounded-xl border border-border bg-surface p-5 sm:p-6">
        {hayErrores && <Alert>{text.reviewFields}</Alert>}
        {problema === 'red' && <Alert>{text.networkError}</Alert>}
        {problema === 'inesperado' && <Alert>{text.unexpectedError}</Alert>}
        {problema === 'conflicto' && (
          <Alert>
            <p>{text.conflict}</p>
            {onRecargar && (
              <Button variant="secondary" className="mt-2" onClick={onRecargar}>
                {text.reload}
              </Button>
            )}
          </Alert>
        )}
        {registro?.estado === 'Inactivo' && <Alert tone="neutral">{preguntasText.inactiveNotice}</Alert>}

        {registro ? (
          <CompetenciaResumen
            codigo={registro.codigo}
            nombre={registro.nombre}
            descripcion={registro.descripcion}
            tipo={registro.tipo}
            estado={registro.estado}
          />
        ) : (
          <>
            <Controller
              control={control}
              name="competencia_id"
              render={({ field }) => (
                <Combobox
                  id="competencia"
                  label={text.competencia}
                  placeholder={text.competenciaPlaceholder}
                  emptyText={text.competenciaEmpty}
                  options={disponibles.map((competencia) => ({
                    value: String(competencia.competencia_id),
                    label: competencia.nombre,
                    description: competencia.codigo,
                  }))}
                  value={field.value === undefined ? null : String(field.value)}
                  onChange={(valor) => field.onChange(valor === null ? undefined : Number(valor))}
                  error={errors.competencia_id?.message}
                />
              )}
            />
            {elegida && <CompetenciaResumen nombre={elegida.nombre} descripcion={elegida.descripcion} tipo={elegida.tipo} />}
          </>
        )}

        <fieldset className="flex min-w-0 flex-col gap-4 rounded-lg border border-border p-4">
          <legend className="px-1 text-sm font-semibold">{text.questionsLegend}</legend>
          {POSICIONES.map((indice) => (
            <TextField
              key={indice}
              id={`pregunta-${indice + 1}`}
              label={text.question(indice + 1)}
              maxLength={LIMITES.texto}
              hint={`${valores?.[indice]?.length ?? 0}/${LIMITES.texto}`}
              error={errors.preguntas?.[indice]?.message}
              {...register(`preguntas.${indice}`)}
            />
          ))}
        </fieldset>

        <div className="flex justify-end gap-3">
          <Link
            to="/preguntas"
            className="inline-flex min-h-11 items-center justify-center rounded-lg border border-border bg-surface px-5 text-[15px] font-semibold hover:bg-soft"
          >
            {text.cancel}
          </Link>
          <Button type="submit" loading={guardando}>
            {guardando ? text.saving : text.save}
          </Button>
        </div>
      </form>
    </div>
  )
}

function EsqueletoFormulario() {
  return (
    <div role="status" aria-label={text.loadingLabel} className="mx-auto flex max-w-190 flex-col gap-4">
      <Skeleton className="h-6 w-1/3" />
      <Skeleton className="h-8 w-1/2" />
      <Skeleton className="h-96" />
    </div>
  )
}

function NoExiste() {
  return (
    <div role="alert" className="mx-auto flex max-w-md flex-col items-center gap-3 py-12 text-center">
      <h1 className="text-xl font-bold">{text.notFoundTitle}</h1>
      <p className="text-sm text-muted">{text.notFoundDescription}</p>
      <Link to="/preguntas" className="font-semibold text-accent underline">
        {text.backToList}
      </Link>
    </div>
  )
}

/** Crear (`/preguntas/nuevo`) y editar (`/preguntas/:competenciaId/editar`) comparten el formulario. */
export function PreguntasFormPage() {
  const { competenciaId } = useParams()
  const id = competenciaId === undefined ? null : Number(competenciaId)
  const editando = id !== null
  const detalle = usePregunta(id ?? 0, editando, true)
  const disponibles = useCompetenciasDisponibles(!editando)

  if (editando) {
    // Con datos en caché se espera la lectura fresca antes de montar el formulario.
    if (detalle.isPending || (detalle.isFetching && !detalle.isFetchedAfterMount)) return <EsqueletoFormulario />

    if (detalle.isError) {
      return toApiError(detalle.error).status === 404 ? (
        <NoExiste />
      ) : (
        <ErrorState
          title={text.loadErrorTitle}
          description={text.loadErrorDescription}
          retryLabel={preguntasText.list.retry}
          onRetry={() => void detalle.refetch()}
        />
      )
    }

    return (
      // Al recargar tras un conflicto se lee de nuevo y `key` monta el formulario otra vez con la versión actual.
      <PreguntasForm key={detalle.dataUpdatedAt} registro={detalle.data} onRecargar={() => void detalle.refetch()} />
    )
  }

  if (disponibles.isPending || (disponibles.isFetching && !disponibles.isFetchedAfterMount)) return <EsqueletoFormulario />

  if (disponibles.isError) {
    return (
      <ErrorState
        title={text.loadErrorTitle}
        description={text.loadErrorDescription}
        retryLabel={preguntasText.list.retry}
        onRetry={() => void disponibles.refetch()}
      />
    )
  }

  if (disponibles.data.length === 0) {
    return (
      <div role="status" className="mx-auto flex max-w-md flex-col items-center gap-3 py-12 text-center">
        <h1 className="text-xl font-bold">{text.noneAvailableTitle}</h1>
        <p className="text-sm text-muted">{text.noneAvailableDescription}</p>
        <Link to="/preguntas" className="font-semibold text-accent underline">
          {text.backToList}
        </Link>
      </div>
    )
  }

  return <PreguntasForm disponibles={disponibles.data} />
}
