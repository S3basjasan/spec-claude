import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { FormProvider, useForm, useWatch, type Path } from 'react-hook-form'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { TextField } from '../../../../shared/ui/TextField'
import type { CompetenciaDisponible, DatosPuesto, PuestoDetalle } from '../api'
import { CompetenciasFieldset } from '../components/CompetenciasFieldset'
import { FuncionesFieldset } from '../components/FuncionesFieldset'
import { RequisitosFieldset } from '../components/RequisitosFieldset'
import { useActualizarPuesto, useCrearPuesto } from '../hooks/useGuardarPuesto'
import { puestosKey, useCompetenciasDisponibles, usePuesto } from '../hooks/usePuestos'
import { puestosText } from '../text'
import { LIMITES, puestoSchema, type PuestoFormValues } from '../validation'

const text = puestosText.form

type Problema = 'red' | 'conflicto' | 'noExiste' | 'inesperado' | null

/** Campos de la API que se muestran junto al campo o la sección correspondiente. */
const CAMPOS_DEL_FORMULARIO = /^(nombre|descripcion|mision|funciones|requisitos|competencias)(\.|$)/

function valoresVacios(): PuestoFormValues {
  return {
    nombre: '',
    descripcion: '',
    mision: '',
    funciones: [{ tipo: 'Esencial', descripcion: '' }],
    requisitos: [
      { tipo: 'Formacion', descripcion: '' },
      { tipo: 'Experiencia', descripcion: '' },
    ],
    competencias: [{ competencia_id: undefined as unknown as number, grado_requerido: '' }],
  }
}

function valoresDe(puesto: PuestoDetalle): PuestoFormValues {
  return {
    nombre: puesto.nombre,
    descripcion: puesto.descripcion,
    mision: puesto.mision,
    funciones: [...puesto.funciones].sort((a, b) => a.orden - b.orden).map((f) => ({ tipo: f.tipo, descripcion: f.descripcion })),
    requisitos: puesto.requisitos.map((r) => ({ tipo: r.tipo, descripcion: r.descripcion })),
    competencias: puesto.competencias.map((c) => ({ competencia_id: c.competencia_id, grado_requerido: c.grado_requerido })),
  }
}

function datosDe(valores: PuestoFormValues): DatosPuesto {
  return {
    nombre: valores.nombre,
    descripcion: valores.descripcion,
    mision: valores.mision,
    funciones: valores.funciones.map((f) => ({ descripcion: f.descripcion, tipo: f.tipo })),
    requisitos: valores.requisitos.map((r) => ({ tipo: r.tipo, descripcion: r.descripcion })),
    competencias: valores.competencias.map((c) => ({ competencia_id: c.competencia_id, grado_requerido: c.grado_requerido })),
  }
}

interface FormularioProps {
  puesto?: PuestoDetalle
  disponibles: CompetenciaDisponible[]
  onRecargar?: () => void
}

function PuestoForm({ puesto, disponibles, onRecargar }: FormularioProps) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const editando = puesto !== undefined
  const crear = useCrearPuesto()
  const actualizar = useActualizarPuesto(puesto?.id ?? 0, puesto?.version ?? 0)
  const guardando = crear.isPending || actualizar.isPending
  const [problema, setProblema] = useState<Problema>(null)

  const metodos = useForm<PuestoFormValues>({
    resolver: zodResolver(puestoSchema),
    defaultValues: puesto ? valoresDe(puesto) : valoresVacios(),
  })
  const {
    register,
    handleSubmit,
    setError,
    control,
    formState: { errors },
  } = metodos

  const nombre = useWatch({ control, name: 'nombre' })
  const descripcion = useWatch({ control, name: 'descripcion' })
  const mision = useWatch({ control, name: 'mision' })

  const onSubmit = (valores: PuestoFormValues) => {
    if (guardando) return
    setProblema(null)
    const datos = datosDe(valores)
    const opciones = {
      onSuccess: () => navigate('/puestos', { state: { aviso: editando ? puestosText.list.updated : puestosText.list.created } }),
      onError: (error: unknown) => {
        const api = toApiError(error)
        if (api.isNetworkError) return setProblema('red')
        if (api.status === 409) return setProblema('conflicto')
        if (api.status === 404) return setProblema('noExiste')
        if (api.status === 422) {
          const campos = Object.entries(api.fieldErrors).filter(([campo]) => CAMPOS_DEL_FORMULARIO.test(campo))
          for (const [campo, mensajes] of campos) {
            // Una competencia que ya no está disponible: se refresca la lista de las elegibles.
            if (/^competencias\.\d+\.competencia_id$/.test(campo)) {
              void queryClient.invalidateQueries({ queryKey: [...puestosKey, 'competencias-disponibles'] })
            }
            setError(campo as Path<PuestoFormValues>, { message: mensajes[0] })
          }
          if (campos.length > 0) return
        }
        setProblema('inesperado')
      },
    }
    if (editando) actualizar.mutate(datos, opciones)
    else crear.mutate(datos, opciones)
  }

  const hayErrores = Object.keys(errors).length > 0

  return (
    <div className="mx-auto flex max-w-190 flex-col gap-5">
      <Breadcrumb
        label={text.breadcrumbLabel}
        items={[
          { label: text.breadcrumbList, to: '/puestos' },
          { label: editando ? text.editBreadcrumb : text.newBreadcrumb },
        ]}
      />
      <h1 className="text-[28px] font-bold leading-tight tracking-tight">{editando ? text.editTitle : text.newTitle}</h1>

      <FormProvider {...metodos}>
        <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5 rounded-xl border border-border bg-surface p-5 sm:p-6">
          {hayErrores && <Alert>{text.reviewFields}</Alert>}
          {problema === 'red' && <Alert>{text.networkError}</Alert>}
          {problema === 'inesperado' && <Alert>{text.unexpectedError}</Alert>}
          {problema === 'noExiste' && <Alert>{text.notFoundTitle}</Alert>}
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
          {puesto?.estado === 'Inactivo' && <Alert tone="neutral">{puestosText.inactiveNotice}</Alert>}

          <fieldset className="flex min-w-0 flex-col gap-4 rounded-lg border border-border p-4">
            <legend className="px-1 text-base font-bold">{text.generalLegend}</legend>
            <TextField
              id="codigo"
              label={text.code}
              readOnly
              value={puesto ? puesto.codigo : text.codeOnSave}
              hint={puesto ? text.codeReadOnly : undefined}
              className="max-w-50 bg-bg text-muted"
            />
            <TextField
              id="nombre"
              label={text.name}
              maxLength={LIMITES.nombre}
              hint={`${nombre?.length ?? 0}/${LIMITES.nombre}`}
              error={errors.nombre?.message}
              {...register('nombre')}
            />
            <TextareaField
              id="descripcion"
              label={text.description}
              rows={2}
              maxLength={LIMITES.descripcion}
              currentLength={descripcion?.length ?? 0}
              error={errors.descripcion?.message}
              {...register('descripcion')}
            />
            <TextareaField
              id="mision"
              label={text.mission}
              rows={3}
              maxLength={LIMITES.mision}
              currentLength={mision?.length ?? 0}
              error={errors.mision?.message}
              {...register('mision')}
            />
          </fieldset>

          <FuncionesFieldset />
          <RequisitosFieldset />
          <CompetenciasFieldset disponibles={disponibles} asignadas={puesto?.competencias} />

          <div className="flex justify-end gap-3">
            <Link
              to="/puestos"
              className="inline-flex min-h-11 items-center justify-center rounded-lg border border-border bg-surface px-5 text-[15px] font-semibold hover:bg-soft"
            >
              {text.cancel}
            </Link>
            <Button type="submit" loading={guardando}>
              {guardando ? text.saving : text.save}
            </Button>
          </div>
        </form>
      </FormProvider>
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
      <Link to="/puestos" className="font-semibold text-accent underline">
        {text.backToList}
      </Link>
    </div>
  )
}

/** Crear (`/puestos/nuevo`) y editar (`/puestos/:puestoId/editar`) comparten el formulario. */
export function PuestoFormPage() {
  const { puestoId } = useParams()
  const id = puestoId === undefined ? null : Number(puestoId)
  const editando = id !== null
  const detalle = usePuesto(id ?? 0, editando, true)
  const disponibles = useCompetenciasDisponibles()

  // Con datos en caché se espera la lectura fresca antes de montar el formulario.
  if (disponibles.isPending || (disponibles.isFetching && !disponibles.isFetchedAfterMount)) return <EsqueletoFormulario />

  if (disponibles.isError) {
    return (
      <ErrorState
        title={text.loadErrorTitle}
        description={text.loadErrorDescription}
        retryLabel={puestosText.list.retry}
        onRetry={() => void disponibles.refetch()}
      />
    )
  }

  if (editando) {
    if (detalle.isPending || (detalle.isFetching && !detalle.isFetchedAfterMount)) return <EsqueletoFormulario />

    if (detalle.isError) {
      return toApiError(detalle.error).status === 404 ? (
        <NoExiste />
      ) : (
        <ErrorState
          title={text.loadErrorTitle}
          description={text.loadErrorDescription}
          retryLabel={puestosText.list.retry}
          onRetry={() => void detalle.refetch()}
        />
      )
    }

    return (
      // Al recargar tras un conflicto se lee de nuevo y `key` monta el formulario otra vez con la versión actual.
      <PuestoForm key={detalle.dataUpdatedAt} puesto={detalle.data} disponibles={disponibles.data} onRecargar={() => void detalle.refetch()} />
    )
  }

  if (disponibles.data.length === 0) {
    return (
      <div role="status" className="mx-auto flex max-w-md flex-col items-center gap-3 py-12 text-center">
        <h1 className="text-xl font-bold">{text.noneAvailableTitle}</h1>
        <p className="text-sm text-muted">{text.noneAvailableDescription}</p>
        <Link to="/puestos" className="font-semibold text-accent underline">
          {text.backToList}
        </Link>
      </div>
    )
  }

  return <PuestoForm disponibles={disponibles.data} />
}
