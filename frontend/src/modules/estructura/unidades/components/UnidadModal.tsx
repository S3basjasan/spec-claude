import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField, type SelectOption } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextField } from '../../../../shared/ui/TextField'
import { MAX_NOMBRE, type OpcionesDeUnidad, type UnidadDetalle } from '../api'
import { useActualizarUnidad, useCrearUnidad } from '../hooks/useGuardarUnidad'
import { useOpcionesDeUnidad, useUnidad } from '../hooks/useUnidades'
import { unidadesText } from '../text'
import { datosDeFormulario, esquemaDeUnidad, type UnidadFormValues } from '../validation'
import { UnidadInfo } from './UnidadInfo'

const text = unidadesText.unitModal

type Problema = 'red' | 'conflicto' | 'noExiste' | 'inesperado' | null

/** Campos de la API que se muestran junto al campo correspondiente. */
const CAMPOS = ['nombre', 'unidad_padre_id'] as const

/** Sangría de texto de una opción según su nivel (los `<option>` no admiten estilos de sangría fiables). */
const sangria = (nivel: number) => '   '.repeat(nivel)

export type ModoDeUnidad = { modo: 'crear'; padreId?: number } | { modo: 'editar'; id: number }

interface UnidadModalProps {
  modo: ModoDeUnidad
  onCerrar: () => void
  /** Se llama con la unidad guardada y si fue una edición (`true`) o una creación (`false`). */
  onGuardada: (unidad: UnidadDetalle, editando: boolean) => void
  /** La unidad ya no existe: quien llama cierra el modal y lo avisa. */
  onNoExiste: () => void
}

/**
 * Modal de crear y editar una unidad. Carga lo que necesita (opciones y, al editar, el detalle) siempre con una lectura
 * nueva; la unidad se congela al abrir: su `version` es la que se envía, aunque el detalle se vuelva a leer.
 */
export function UnidadModal({ modo, onCerrar, onGuardada, onNoExiste }: UnidadModalProps) {
  const editando = modo.modo === 'editar'
  const id = modo.modo === 'editar' ? modo.id : undefined
  const opciones = useOpcionesDeUnidad(id)
  const detalle = useUnidad(id ?? 0, editando, true)
  const [abierta, setAbierta] = useState<UnidadDetalle | null>(null)
  const [recarga, setRecarga] = useState(0)

  // Solo se congela una lectura hecha después de abrir el modal: lo que haya en caché puede ser anterior a un guardado
  // reciente y traería una versión vencida.
  if (editando && abierta === null && detalle.isFetchedAfterMount && detalle.data !== undefined) setAbierta(detalle.data)

  // Tras un conflicto la invalidación ya volvió a pedir ambas lecturas: se reutiliza la que esté en curso.
  const recargar = async () => {
    const [nueva] = await Promise.all([detalle.refetch({ cancelRefetch: false }), opciones.refetch({ cancelRefetch: false })])
    if (nueva.data) {
      setAbierta(nueva.data)
      setRecarga((n) => n + 1)
    }
  }

  const titulo = abierta ? text.editTitle(abierta.codigo) : editando ? text.editTitle('…') : text.createTitle
  const detalleFallo = editando && detalle.isError && abierta === null
  const noExiste = detalleFallo && toApiError(detalle.error).status === 404

  if (noExiste) {
    return (
      <Modal open title={titulo} onClose={onCerrar} closeLabel={text.close} footer={<Button variant="secondary" onClick={onCerrar}>{text.cancel}</Button>}>
        <Alert>{text.notFound}</Alert>
      </Modal>
    )
  }

  if (opciones.isError || detalleFallo) {
    return (
      <Modal open title={titulo} onClose={onCerrar} closeLabel={text.close} footer={<Button variant="secondary" onClick={onCerrar}>{text.cancel}</Button>}>
        <ErrorState
          title={text.loadErrorTitle}
          description={text.loadErrorDescription}
          retryLabel={text.retry}
          onRetry={() => void (opciones.isError ? opciones.refetch() : detalle.refetch())}
        />
      </Modal>
    )
  }

  if (!opciones.isFetchedAfterMount || opciones.data === undefined || (editando && abierta === null)) {
    return (
      <Modal open title={titulo} onClose={onCerrar} closeLabel={text.close}>
        <div role="status" aria-label={text.loadingLabel} className="flex flex-col gap-3">
          <Skeleton className="h-11" />
          <Skeleton className="h-11" />
        </div>
      </Modal>
    )
  }

  return (
    <FormularioDeUnidad
      key={`${id ?? 'nueva'}-${recarga}`}
      unidad={abierta ?? undefined}
      padreInicial={modo.modo === 'crear' ? modo.padreId : undefined}
      opciones={opciones.data}
      onCerrar={onCerrar}
      onGuardada={onGuardada}
      onNoExiste={onNoExiste}
      onRecargar={editando ? () => void recargar() : undefined}
    />
  )
}

interface FormularioDeUnidadProps {
  unidad?: UnidadDetalle
  padreInicial?: number
  opciones: OpcionesDeUnidad
  onCerrar: () => void
  onGuardada: (unidad: UnidadDetalle, editando: boolean) => void
  onNoExiste: () => void
  onRecargar?: () => void
}

function FormularioDeUnidad({ unidad, padreInicial, opciones, onCerrar, onGuardada, onNoExiste, onRecargar }: FormularioDeUnidadProps) {
  const editando = unidad !== undefined
  const esRaiz = editando && unidad.unidad_padre === null
  const crear = useCrearUnidad()
  const actualizar = useActualizarUnidad(unidad?.id ?? 0, unidad?.version ?? 0)
  const guardando = crear.isPending || actualizar.isPending
  const [problema, setProblema] = useState<Problema>(null)
  const esquema = useMemo(() => esquemaDeUnidad(!esRaiz), [esRaiz])

  const {
    register,
    handleSubmit,
    setError,
    control,
    formState: { errors },
  } = useForm<UnidadFormValues>({
    resolver: zodResolver(esquema),
    defaultValues: {
      nombre: unidad?.nombre ?? '',
      unidad_padre_id: unidad?.unidad_padre ? String(unidad.unidad_padre.id) : padreInicial !== undefined ? String(padreInicial) : '',
    },
  })
  const nombre = useWatch({ control, name: 'nombre' })
  const padreElegido = useWatch({ control, name: 'unidad_padre_id' })

  // Opciones de la unidad padre. Al crear: todas las unidades en orden de árbol (las inactivas y las del último nivel,
  // deshabilitadas). Al editar: solo las candidatas válidas; la padre actual se conserva aunque ya no lo sea.
  const opcionesPadre: SelectOption[] = []
  if (esRaiz) {
    opcionesPadre.push({ value: '', label: text.noParent })
  } else {
    if (!editando) opcionesPadre.push({ value: '', label: text.parentPlaceholder })
    const padreActual = unidad?.unidad_padre
    if (padreActual && !opciones.unidades.some((candidata) => candidata.id === padreActual.id)) {
      opcionesPadre.push({ value: String(padreActual.id), label: `${padreActual.nombre} (${padreActual.codigo}) · ${text.optionKept}` })
    }
    for (const candidata of opciones.unidades) {
      const nota = !candidata.admite_hijas ? text.optionLast : candidata.estado === 'Inactiva' ? text.optionInactive : null
      opcionesPadre.push({
        value: String(candidata.id),
        label: `${sangria(candidata.nivel_arbol)}${candidata.nombre} (${candidata.codigo})${nota ? ` · ${nota}` : ''}`,
        disabled: !candidata.habilitada,
      })
    }
  }

  // Tipo de la nueva unidad: el del nivel siguiente al de la padre elegida (el servidor lo vuelve a calcular).
  const padre = opciones.unidades.find((candidata) => String(candidata.id) === padreElegido)
  const tipoNuevo = padre ? opciones.tipos.find((tipo) => tipo.nivel === padre.tipo.nivel + 1) : undefined
  const tipoTexto = editando ? unidad.tipo.nombre : tipoNuevo ? text.typeWillBe(tipoNuevo.nombre) : text.typePending

  const onSubmit = (valores: UnidadFormValues) => {
    if (guardando) return
    setProblema(null)
    const datos = datosDeFormulario(valores)
    const resultado = {
      onSuccess: (guardada: UnidadDetalle) => onGuardada(guardada, editando),
      onError: (error: unknown) => {
        const api = toApiError(error)
        if (api.isNetworkError) return setProblema('red')
        if (api.status === 409) return setProblema('conflicto')
        if (api.status === 404) {
          onNoExiste()
          return setProblema('noExiste')
        }
        if (api.status === 422) {
          const campos = CAMPOS.filter((campo) => api.fieldErrors[campo]?.length)
          for (const campo of campos) setError(campo, { message: api.fieldErrors[campo][0] })
          if (campos.length > 0) return
        }
        setProblema('inesperado')
      },
    }
    if (editando) actualizar.mutate(datos, resultado)
    else crear.mutate(datos, resultado)
  }

  const hayErrores = Object.keys(errors).length > 0

  return (
    <Modal
      open
      title={editando ? text.editTitle(unidad.codigo) : text.createTitle}
      onClose={onCerrar}
      busy={guardando}
      closeLabel={text.close}
      footer={
        <>
          <Button variant="secondary" onClick={onCerrar} disabled={guardando}>
            {text.cancel}
          </Button>
          <Button type="submit" form="formulario-unidad" loading={guardando}>
            {guardando ? text.saving : text.save}
          </Button>
        </>
      }
    >
      <form id="formulario-unidad" onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-4">
        {unidad && <UnidadInfo unidad={unidad} />}

        {hayErrores && <Alert>{text.reviewFields}</Alert>}
        {problema === 'red' && <Alert>{text.networkError}</Alert>}
        {problema === 'inesperado' && <Alert>{text.unexpectedError}</Alert>}
        {problema === 'noExiste' && <Alert>{text.notFound}</Alert>}
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

        <TextField
          id="unidad-nombre"
          label={text.name}
          maxLength={MAX_NOMBRE}
          hint={text.nameCounter(nombre?.length ?? 0)}
          error={errors.nombre?.message}
          {...register('nombre')}
        />
        {esRaiz ? (
          // La raíz no tiene unidad padre y no se reubica: el selector queda deshabilitado y fuera del formulario.
          <SelectField id="unidad-padre" label={text.parent} options={opcionesPadre} disabled value="" onChange={() => undefined} />
        ) : (
          <SelectField id="unidad-padre" label={text.parent} options={opcionesPadre} error={errors.unidad_padre_id?.message} {...register('unidad_padre_id')} />
        )}
        <TextField
          id="unidad-tipo"
          label={editando ? text.typeExisting : text.typeNew}
          readOnly
          value={tipoTexto}
          className="bg-bg text-muted"
        />
      </form>
    </Modal>
  )
}
