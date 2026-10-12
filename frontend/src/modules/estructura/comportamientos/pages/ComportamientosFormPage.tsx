import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { Controller, useForm, useWatch, type FieldErrors, type Path } from 'react-hook-form'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { Combobox } from '../../../../shared/ui/Combobox'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { ListaDeCampos } from '../../../../shared/ui/ListaDeCampos'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { LETRAS_GRADO, type CompetenciaDisponible, type LetraGrado, type RegistroDetalle } from '../api'
import { CompetenciaResumen } from '../components/CompetenciaResumen'
import { useCompetenciasDisponibles, useComportamiento } from '../hooks/useComportamientos'
import { useActualizarComportamientos, useCrearComportamientos } from '../hooks/useGuardarComportamientos'
import { comportamientosText } from '../text'
import { comportamientosSchema, LIMITES, type ComportamientosFormValues } from '../validation'

const text = comportamientosText.form

type Problema = 'red' | 'conflicto' | 'inesperado' | null

type ErroresGrado = (Array<{ message?: string } | undefined> & { message?: string; root?: { message?: string } }) | undefined

function erroresDeGrado(errors: FieldErrors<ComportamientosFormValues>, letra: LetraGrado, cantidad: number) {
  const grupo = errors.comportamientos?.[letra] as ErroresGrado
  return {
    items: Array.from({ length: cantidad }, (_, indice) => grupo?.[indice]?.message),
    grupo: grupo?.message ?? grupo?.root?.message,
  }
}

interface FormularioProps {
  registro?: RegistroDetalle
  disponibles?: CompetenciaDisponible[]
  onRecargar?: () => void
}

function ComportamientosForm({ registro, disponibles = [], onRecargar }: FormularioProps) {
  const navigate = useNavigate()
  const editando = registro !== undefined
  const crear = useCrearComportamientos()
  const actualizar = useActualizarComportamientos(registro?.competencia_id ?? 0, registro?.version ?? 0)
  const guardando = crear.isPending || actualizar.isPending
  const [problema, setProblema] = useState<Problema>(null)

  const {
    handleSubmit,
    setError,
    control,
    formState: { errors },
  } = useForm<ComportamientosFormValues>({
    resolver: zodResolver(comportamientosSchema),
    defaultValues: registro
      ? { competencia_id: registro.competencia_id, comportamientos: registro.comportamientos }
      : { comportamientos: { A: [''], B: [''], C: [''], D: [''] } },
  })

  const competenciaId = useWatch({ control, name: 'competencia_id' })
  const valores = useWatch({ control, name: 'comportamientos' })
  const elegida = disponibles.find((competencia) => competencia.competencia_id === competenciaId)

  const onSubmit = (values: ComportamientosFormValues) => {
    if (guardando) return
    setProblema(null)
    const opciones = {
      onSuccess: () =>
        navigate('/comportamientos', {
          state: { aviso: editando ? comportamientosText.list.updated : comportamientosText.list.created },
        }),
      onError: (error: unknown) => {
        const api = toApiError(error)
        if (api.isNetworkError) return setProblema('red')
        if (api.status === 409) return setProblema('conflicto')
        if (api.status === 422) {
          const campos = Object.entries(api.fieldErrors)
          for (const [campo, mensajes] of campos) {
            if (campo === 'competencia_id' || /^comportamientos\.[A-D](\.\d+)?$/.test(campo)) {
              setError(campo as Path<ComportamientosFormValues>, { message: mensajes[0] })
            }
          }
          if (campos.length > 0) return
        }
        setProblema('inesperado')
      },
    }
    if (editando) actualizar.mutate(values.comportamientos, opciones)
    else crear.mutate({ competenciaId: values.competencia_id, comportamientos: values.comportamientos }, opciones)
  }

  const hayErrores = Object.keys(errors).length > 0

  return (
    <div className="mx-auto flex max-w-190 flex-col gap-5">
      <Breadcrumb
        label={text.breadcrumbLabel}
        items={[
          { label: text.breadcrumbList, to: '/comportamientos' },
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
        {registro?.estado === 'Inactivo' && <Alert tone="neutral">{comportamientosText.inactiveNotice}</Alert>}

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

        {LETRAS_GRADO.map((letra) => {
          const lista = valores?.[letra] ?? ['']
          const { items, grupo } = erroresDeGrado(errors, letra, lista.length)
          return (
            <Controller
              key={letra}
              control={control}
              name={`comportamientos.${letra}`}
              render={({ field }) => (
                <ListaDeCampos
                  id={`comportamiento-${letra}`}
                  legend={text.gradeLegend(letra)}
                  values={field.value}
                  onChange={field.onChange}
                  min={LIMITES.minimo}
                  max={LIMITES.maximo}
                  maxLength={LIMITES.texto}
                  itemLabel={(posicion) => text.itemLabel(letra, posicion)}
                  addLabel={text.add}
                  removeLabel={(posicion) => text.remove(letra, posicion)}
                  itemErrors={items}
                  error={grupo}
                />
              )}
            />
          )
        })}

        <div className="flex justify-end gap-3">
          <Link
            to="/comportamientos"
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
      <Link to="/comportamientos" className="font-semibold text-accent underline">
        {text.backToList}
      </Link>
    </div>
  )
}

/** Crear (`/comportamientos/nuevo`) y editar (`/comportamientos/:competenciaId/editar`) comparten el formulario. */
export function ComportamientosFormPage() {
  const { competenciaId } = useParams()
  const id = competenciaId === undefined ? null : Number(competenciaId)
  const editando = id !== null
  const detalle = useComportamiento(id ?? 0, editando, true)
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
          retryLabel={comportamientosText.list.retry}
          onRetry={() => void detalle.refetch()}
        />
      )
    }

    return (
      // Al recargar tras un conflicto se lee de nuevo y `key` monta el formulario otra vez con la versión actual.
      <ComportamientosForm key={detalle.dataUpdatedAt} registro={detalle.data} onRecargar={() => void detalle.refetch()} />
    )
  }

  if (disponibles.isPending || (disponibles.isFetching && !disponibles.isFetchedAfterMount)) return <EsqueletoFormulario />

  if (disponibles.isError) {
    return (
      <ErrorState
        title={text.loadErrorTitle}
        description={text.loadErrorDescription}
        retryLabel={comportamientosText.list.retry}
        onRetry={() => void disponibles.refetch()}
      />
    )
  }

  if (disponibles.data.length === 0) {
    return (
      <div role="status" className="mx-auto flex max-w-md flex-col items-center gap-3 py-12 text-center">
        <h1 className="text-xl font-bold">{text.noneAvailableTitle}</h1>
        <p className="text-sm text-muted">{text.noneAvailableDescription}</p>
        <Link to="/comportamientos" className="font-semibold text-accent underline">
          {text.backToList}
        </Link>
      </div>
    )
  }

  return <ComportamientosForm disponibles={disponibles.data} />
}
