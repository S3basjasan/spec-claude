import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { TextField } from '../../../../shared/ui/TextField'
import { LETRAS_GRADO, type CompetenciaDetalle } from '../api'
import { useCompetencia } from '../hooks/useCompetencias'
import { useActualizarCompetencia, useCrearCompetencia } from '../hooks/useGuardarCompetencia'
import { competenciasText } from '../text'
import { competenciaSchema, LIMITES, type CompetenciaFormValues } from '../validation'

const text = competenciasText.form

type Problema = 'red' | 'conflicto' | 'inesperado' | null

function valoresIniciales(competencia?: CompetenciaDetalle): Partial<CompetenciaFormValues> {
  if (!competencia) return { nombre: '', descripcion: '', grados: { A: '', B: '', C: '', D: '' } }
  const grados = { A: '', B: '', C: '', D: '' }
  for (const grado of competencia.grados) grados[grado.grado] = grado.descripcion
  return { nombre: competencia.nombre, descripcion: competencia.descripcion, tipo: competencia.tipo, grados }
}

interface FormularioProps {
  competencia?: CompetenciaDetalle
  onRecargar?: () => void
}

function CompetenciaForm({ competencia, onRecargar }: FormularioProps) {
  const navigate = useNavigate()
  const editando = competencia !== undefined
  const crear = useCrearCompetencia()
  const actualizar = useActualizarCompetencia(competencia?.id ?? 0, competencia?.version ?? 0)
  const guardando = crear.isPending || actualizar.isPending
  const [problema, setProblema] = useState<Problema>(null)

  const {
    register,
    handleSubmit,
    setError,
    control,
    formState: { errors },
  } = useForm<CompetenciaFormValues>({
    resolver: zodResolver(competenciaSchema),
    defaultValues: valoresIniciales(competencia),
  })

  const nombre = useWatch({ control, name: 'nombre' }) ?? ''
  const descripcion = useWatch({ control, name: 'descripcion' }) ?? ''
  const grados = useWatch({ control, name: 'grados' })

  const onSubmit = (values: CompetenciaFormValues) => {
    if (guardando) return
    setProblema(null)
    const opciones = {
      onSuccess: () =>
        navigate('/competencias', {
          state: { aviso: editando ? competenciasText.list.updated : competenciasText.list.created },
        }),
      onError: (error: unknown) => {
        const api = toApiError(error)
        if (api.isNetworkError) return setProblema('red')
        if (api.status === 409) return setProblema('conflicto')
        if (api.status === 422) {
          const campos = Object.entries(api.fieldErrors)
          for (const [campo, mensajes] of campos) {
            if (campo === 'nombre' || campo === 'descripcion' || campo === 'tipo') {
              setError(campo, { message: mensajes[0] })
            } else if (/^grados\.[A-D]$/.test(campo)) {
              setError(campo as 'grados.A' | 'grados.B' | 'grados.C' | 'grados.D', { message: mensajes[0] })
            }
          }
          if (campos.length > 0) return
        }
        setProblema('inesperado')
      },
    }
    if (editando) actualizar.mutate(values, opciones)
    else crear.mutate(values, opciones)
  }

  const hayErrores = Object.keys(errors).length > 0
  const titulo = editando ? text.editTitle : text.newTitle

  return (
    <div className="mx-auto flex max-w-190 flex-col gap-5">
      <Breadcrumb
        label={text.breadcrumbLabel}
        items={[
          { label: text.breadcrumbList, to: '/competencias' },
          { label: editando ? text.editBreadcrumb : text.newBreadcrumb },
        ]}
      />
      <h1 className="text-[28px] font-bold leading-tight tracking-tight">{titulo}</h1>

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

        {competencia && (
          <TextField id="codigo" label={text.code} hint={text.codeHint} value={competencia.codigo} readOnly aria-readonly="true" className="bg-bg text-muted" />
        )}

        <TextField
          id="nombre"
          label={text.name}
          placeholder={text.namePlaceholder}
          maxLength={LIMITES.nombre}
          hint={`${nombre.length}/${LIMITES.nombre}`}
          error={errors.nombre?.message}
          {...register('nombre')}
        />

        <TextareaField
          id="descripcion"
          label={text.description}
          maxLength={LIMITES.texto}
          currentLength={descripcion.length}
          error={errors.descripcion?.message}
          {...register('descripcion')}
        />

        <SelectField
          id="tipo"
          label={text.type}
          error={errors.tipo?.message}
          options={[
            { value: '', label: text.typePlaceholder },
            { value: 'Cardinal', label: competenciasText.types.Cardinal },
            { value: 'Especifica', label: competenciasText.types.Especifica },
          ]}
          {...register('tipo')}
        />

        <fieldset className="flex flex-col gap-4 rounded-lg border border-border p-4">
          <legend className="px-1 text-sm font-semibold">{text.gradesLegend}</legend>
          {LETRAS_GRADO.map((letra) => (
            <TextareaField
              key={letra}
              id={`grado-${letra}`}
              label={text.gradeLabel(letra)}
              maxLength={LIMITES.texto}
              currentLength={grados?.[letra]?.length ?? 0}
              error={errors.grados?.[letra]?.message}
              {...register(`grados.${letra}`)}
            />
          ))}
        </fieldset>

        <div className="flex justify-end gap-3">
          <Link
            to="/competencias"
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

/** Crear (`/competencias/nueva`) y editar (`/competencias/:id/editar`) comparten el formulario. */
export function CompetenciaFormPage() {
  const { id } = useParams()
  const competenciaId = id === undefined ? null : Number(id)
  const editando = competenciaId !== null
  const detalle = useCompetencia(competenciaId ?? 0, editando, true)

  if (!editando) return <CompetenciaForm />

  // Con datos en caché se espera la lectura fresca antes de montar el formulario.
  if (detalle.isPending || (detalle.isFetching && !detalle.isFetchedAfterMount)) {
    return (
      <div role="status" aria-label={text.loadingLabel} className="mx-auto flex max-w-190 flex-col gap-4">
        <Skeleton className="h-6 w-1/3" />
        <Skeleton className="h-8 w-1/2" />
        <Skeleton className="h-96" />
      </div>
    )
  }

  if (detalle.isError) {
    const noExiste = toApiError(detalle.error).status === 404
    return noExiste ? (
      <div role="alert" className="mx-auto flex max-w-md flex-col items-center gap-3 py-12 text-center">
        <h1 className="text-xl font-bold">{text.notFoundTitle}</h1>
        <p className="text-sm text-muted">{text.notFoundDescription}</p>
        <Link to="/competencias" className="font-semibold text-accent underline">
          {text.backToList}
        </Link>
      </div>
    ) : (
      <ErrorState
        title={text.loadErrorTitle}
        description={text.loadErrorDescription}
        retryLabel={competenciasText.list.retry}
        onRetry={() => void detalle.refetch()}
      />
    )
  }

  return (
    // Al recargar tras un conflicto se lee de nuevo y `key` monta el formulario otra vez con la versión actual.
    <CompetenciaForm key={detalle.dataUpdatedAt} competencia={detalle.data} onRecargar={() => void detalle.refetch()} />
  )
}
