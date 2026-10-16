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
import { MODALIDADES, type OpcionesDePosicion, type PosicionDetalle } from '../api'
import { useActualizarPosicion, useCrearPosicion } from '../hooks/useGuardarPosicion'
import { useOpcionesDePosicion, usePosicion } from '../hooks/usePosiciones'
import { posicionesText } from '../text'
import { SIN_SUPERVISOR, datosDeFormulario, esquemaDePosicion, type PosicionFormValues } from '../validation'

const text = posicionesText.modal

type Problema = 'red' | 'conflicto' | 'noExiste' | 'inesperado' | null
type UnidadDeOpciones = OpcionesDePosicion['unidades'][number]

/** Campos de la API que se muestran junto al campo correspondiente. */
const CAMPOS = ['puesto_id', 'unidad_organizacional_id', 'posicion_jefe_id', 'modalidad'] as const

/** Sangría de texto de una opción según su nivel (los `<option>` no admiten estilos de sangría fiables). */
const sangria = (nivel: number) => '   '.repeat(nivel)

export type ModoDePosicion = { modo: 'crear' } | { modo: 'editar'; id: number }

interface PosicionModalProps {
  modo: ModoDePosicion
  onCerrar: () => void
  /** Se llama con la posición guardada y si fue una edición (`true`) o una creación (`false`). */
  onGuardada: (posicion: PosicionDetalle, editando: boolean) => void
  /** La posición ya no existe: quien llama cierra el modal y lo avisa. */
  onNoExiste: () => void
}

/**
 * Modal de crear y editar una posición. Carga lo que necesita (opciones y, al editar, el detalle) siempre con una lectura
 * nueva; la posición se congela al abrir: su `version` es la que se envía, aunque el detalle se vuelva a leer.
 */
export function PosicionModal({ modo, onCerrar, onGuardada, onNoExiste }: PosicionModalProps) {
  const editando = modo.modo === 'editar'
  const id = modo.modo === 'editar' ? modo.id : undefined
  const opciones = useOpcionesDePosicion(id)
  const detalle = usePosicion(id ?? 0, editando, true)
  const [abierta, setAbierta] = useState<PosicionDetalle | null>(null)
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
  const pie = (
    <Button variant="secondary" onClick={onCerrar}>
      {text.cancel}
    </Button>
  )

  if (noExiste) {
    return (
      <Modal open title={titulo} onClose={onCerrar} closeLabel={text.close} footer={pie}>
        <Alert>{text.notFound}</Alert>
      </Modal>
    )
  }

  if (opciones.isError || detalleFallo) {
    return (
      <Modal open title={titulo} onClose={onCerrar} closeLabel={text.close} footer={pie}>
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
    <FormularioDePosicion
      key={`${id ?? 'nueva'}-${recarga}`}
      posicion={abierta ?? undefined}
      opciones={opciones.data}
      onCerrar={onCerrar}
      onGuardada={onGuardada}
      onNoExiste={onNoExiste}
      onRecargar={editando ? () => void recargar() : undefined}
    />
  )
}

interface FormularioDePosicionProps {
  posicion?: PosicionDetalle
  opciones: OpcionesDePosicion
  onCerrar: () => void
  onGuardada: (posicion: PosicionDetalle, editando: boolean) => void
  onNoExiste: () => void
  onRecargar?: () => void
}

/** Candidatas a supervisora para una unidad: las posiciones de esa unidad o de sus superiores, agrupadas por unidad. */
function candidatasDe(opciones: OpcionesDePosicion, unidad: UnidadDeOpciones | undefined): SelectOption[] {
  if (!unidad) return []
  const porId = new Map(opciones.unidades.map((u) => [u.id, u]))
  const resultado: SelectOption[] = []

  for (const unidadId of [unidad.id, ...unidad.superiores]) {
    const nombre = porId.get(unidadId)?.nombre ?? ''
    for (const candidata of opciones.posiciones.filter((p) => p.unidad_organizacional_id === unidadId)) {
      resultado.push({
        value: String(candidata.id),
        label: text.supervisorOption(candidata.codigo, candidata.puesto, candidata.ocupante ?? posicionesText.detail.noOccupant),
        group: nombre,
      })
    }
  }

  return resultado
}

function FormularioDePosicion({ posicion, opciones, onCerrar, onGuardada, onNoExiste, onRecargar }: FormularioDePosicionProps) {
  const editando = posicion !== undefined
  // La máxima autoridad (sin supervisor) solo cambia de modalidad.
  const esMaxima = editando && posicion.supervisor === null
  const crear = useCrearPosicion()
  const actualizar = useActualizarPosicion(posicion?.id ?? 0, posicion?.version ?? 0)
  const guardando = crear.isPending || actualizar.isPending
  const [problema, setProblema] = useState<Problema>(null)
  const esquema = useMemo(() => esquemaDePosicion(!editando), [editando])

  const {
    register,
    handleSubmit,
    setError,
    setValue,
    getValues,
    control,
    formState: { errors },
  } = useForm<PosicionFormValues>({
    resolver: zodResolver(esquema),
    defaultValues: {
      puesto_id: '',
      unidad_organizacional_id: posicion ? String(posicion.unidad.id) : '',
      posicion_jefe_id: posicion ? (posicion.supervisor ? String(posicion.supervisor.id) : SIN_SUPERVISOR) : '',
      modalidad: posicion?.modalidad ?? '',
    },
  })
  const unidadElegidaId = useWatch({ control, name: 'unidad_organizacional_id' })
  const unidadElegida = opciones.unidades.find((u) => String(u.id) === unidadElegidaId)
  const esRaiz = unidadElegida !== undefined && unidadElegida.unidad_padre_id === null

  const unidadPorId = useMemo(() => new Map(opciones.unidades.map((u) => [u.id, u])), [opciones.unidades])
  const candidatas = useMemo(() => candidatasDe(opciones, unidadElegida), [opciones, unidadElegida])

  /** Al editar, la unidad nueva debe ser igual o superior a la de cada subordinada directa vigente. */
  const bajoSubordinadas = (unidad: UnidadDeOpciones): boolean =>
    opciones.unidades_minimas.some((minima) => minima !== unidad.id && !(unidadPorId.get(minima)?.superiores ?? []).includes(unidad.id))

  const opcionesPuesto: SelectOption[] = [
    { value: '', label: text.positionPlaceholder },
    ...opciones.puestos.map((puesto) => ({ value: String(puesto.id), label: `${puesto.nombre} (${puesto.codigo})` })),
  ]

  // La unidad que la posición ya tiene siempre puede conservarse, aunque hoy esté inactiva.
  const opcionesUnidad: SelectOption[] = [
    { value: '', label: text.unitPlaceholder },
    ...opciones.unidades.map((unidad) => {
      const esActual = unidad.id === posicion?.unidad.id
      const motivo =
        unidad.motivo_deshabilitada === 'inactiva'
          ? text.unitInactive
          : unidad.motivo_deshabilitada === 'raiz_ocupada'
            ? text.rootTaken
            : editando && bajoSubordinadas(unidad)
              ? text.unitBelowSubordinates
              : null
      return {
        value: String(unidad.id),
        label: `${sangria(unidad.nivel_arbol)}${unidad.nombre} (${unidad.codigo})${motivo ? ` · ${motivo}` : ''}`,
        disabled: motivo !== null && !esActual,
      }
    }),
  ]

  const opcionesSupervisor: SelectOption[] = esRaiz
    ? [{ value: SIN_SUPERVISOR, label: text.topAuthority }]
    : [{ value: '', label: unidadElegida && candidatas.length === 0 ? text.noCandidates : text.supervisorPlaceholder }, ...candidatas]

  const opcionesModalidad: SelectOption[] = [
    { value: '', label: text.modalityPlaceholder },
    ...MODALIDADES.map((modalidad) => ({ value: modalidad, label: posicionesText.modalities[modalidad] })),
  ]

  /** Al cambiar la unidad el supervisor se ajusta: fijo en la raíz y vacío si deja de ser un candidato válido. */
  const alCambiarUnidad = (valor: string) => {
    const nueva = opciones.unidades.find((u) => String(u.id) === valor)
    if (nueva?.unidad_padre_id === null) {
      setValue('posicion_jefe_id', SIN_SUPERVISOR)
      return
    }
    const actual = getValues('posicion_jefe_id')
    if (!candidatasDe(opciones, nueva).some((c) => c.value === actual)) setValue('posicion_jefe_id', '')
  }

  const onSubmit = (valores: PosicionFormValues) => {
    if (guardando) return
    setProblema(null)
    const datos = datosDeFormulario(valores, !editando)
    const resultado = {
      onSuccess: (guardada: PosicionDetalle) => onGuardada(guardada, editando),
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
      title={editando ? text.editTitle(posicion.codigo) : text.createTitle}
      onClose={onCerrar}
      busy={guardando}
      closeLabel={text.close}
      footer={
        <>
          <Button variant="secondary" onClick={onCerrar} disabled={guardando}>
            {text.cancel}
          </Button>
          <Button type="submit" form="formulario-posicion" loading={guardando}>
            {guardando ? text.saving : text.save}
          </Button>
        </>
      }
    >
      <form id="formulario-posicion" onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-4">
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

        {posicion ? (
          <>
            <TextField id="posicion-codigo" label={text.code} readOnly value={posicion.codigo} className="bg-bg text-muted" />
            <TextField id="posicion-puesto" label={text.position} readOnly value={posicion.puesto.nombre} className="bg-bg text-muted" />
          </>
        ) : (
          <SelectField id="posicion-puesto" label={text.position} options={opcionesPuesto} error={errors.puesto_id?.message} {...register('puesto_id')} />
        )}
        {esMaxima ? (
          <>
            <TextField
              id="posicion-unidad"
              label={text.unit}
              readOnly
              value={`${posicion.unidad.nombre} (${posicion.unidad.codigo})`}
              hint={text.unitReadOnlyHint}
              className="bg-bg text-muted"
            />
            <TextField id="posicion-supervisor" label={text.supervisor} readOnly value={text.topAuthority} className="bg-bg text-muted" />
          </>
        ) : (
          <>
            <SelectField
              id="posicion-unidad"
              label={text.unit}
              options={opcionesUnidad}
              error={errors.unidad_organizacional_id?.message}
              {...register('unidad_organizacional_id', { onChange: (event) => alCambiarUnidad(event.target.value) })}
            />
            <SelectField
              id="posicion-supervisor"
              label={text.supervisor}
              options={opcionesSupervisor}
              error={errors.posicion_jefe_id?.message}
              disabled={unidadElegida === undefined || esRaiz}
              title={unidadElegida === undefined ? text.supervisorDisabledHint : undefined}
              {...register('posicion_jefe_id')}
            />
          </>
        )}
        <SelectField id="posicion-modalidad" label={text.modality} options={opcionesModalidad} error={errors.modalidad?.message} {...register('modalidad')} />
        {posicion && (
          <>
            <TextField
              id="posicion-ocupante"
              label={text.occupant}
              readOnly
              value={posicion.ocupante?.nombre ?? text.noOccupant}
              className="bg-bg text-muted"
            />
            <TextField id="posicion-estado" label={text.status} readOnly value={posicionesText.statuses[posicion.estado]} className="bg-bg text-muted" />
          </>
        )}
        {!posicion && <p className="text-[13px] text-muted">{text.codeHint}</p>}
      </form>
    </Modal>
  )
}
