import { zodResolver } from '@hookform/resolvers/zod'
import { useId } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Modal } from '../../../../shared/ui/Modal'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { CANTIDAD, type CompetenciaDetalle, type Escala } from '../api'
import { categoriasPorDefecto, etiquetasDeEscala } from '../escalas'
import { useCompetencia } from '../hooks/useCompetencias'
import { useEscalas } from '../hooks/useEscalas'
import { useErroresDeGuardado } from '../hooks/useErroresDeGuardado'
import { useActualizarCompetencia, useActualizarComportamientos, useActualizarPreguntas } from '../hooks/useGuardarSeccion'
import { competenciasText } from '../text'
import {
  seccionCompetenciaSchema,
  seccionComportamientosSchema,
  seccionPreguntasSchema,
  type SeccionCompetenciaValues,
  type SeccionComportamientosValues,
  type SeccionPreguntasValues,
} from '../validation'
import {
  CamposDeComportamientos,
  CamposDeCompetencia,
  CamposDePreguntas,
  type ValoresDeComportamientos,
  type ValoresDeCompetencia,
  type ValoresDePreguntas,
} from './camposDeFormulario'

const text = competenciasText.form

export type SeccionEditable = 'competencia' | 'comportamientos' | 'preguntas'

const TITULOS: Record<SeccionEditable, string> = {
  competencia: text.editCompetenciaTitle,
  comportamientos: text.editBehaviorsTitle,
  preguntas: text.editQuestionsTitle,
}

const MENSAJES: Record<SeccionEditable, string> = {
  competencia: competenciasText.notices.updated,
  comportamientos: competenciasText.notices.behaviorsUpdated,
  preguntas: competenciasText.notices.questionsUpdated,
}

/** Rellena con textos vacíos hasta la cantidad fija: una competencia heredada puede traer menos. */
function completar(textos: string[], cantidad: number): string[] {
  return Array.from({ length: cantidad }, (_, indice) => textos[indice] ?? '')
}

interface InternoProps {
  competencia: CompetenciaDetalle
  escalas: Escala[]
  onClose: () => void
  onGuardado: (mensaje: string) => void
  onRecargar: () => void
}

/** Pie común: cancelar y guardar; el guardado se dispara con el `form` del cuerpo. */
function Pie({ formId, guardando, onClose }: { formId: string; guardando: boolean; onClose: () => void }) {
  return (
    <>
      <Button variant="secondary" onClick={onClose} disabled={guardando}>
        {text.cancel}
      </Button>
      <Button type="submit" form={formId} loading={guardando}>
        {guardando ? text.saving : text.saveChanges}
      </Button>
    </>
  )
}

function AvisosDeGuardado({ problema, hayErrores, onRecargar }: { problema: 'red' | 'conflicto' | 'inesperado' | null; hayErrores: boolean; onRecargar: () => void }) {
  return (
    <>
      {hayErrores && <Alert>{text.reviewFields}</Alert>}
      {problema === 'red' && <Alert>{text.networkError}</Alert>}
      {problema === 'inesperado' && <Alert>{text.unexpectedError}</Alert>}
      {problema === 'conflicto' && (
        <Alert>
          <p>{text.conflict}</p>
          <Button variant="secondary" className="mt-2" onClick={onRecargar}>
            {text.reload}
          </Button>
        </Alert>
      )}
    </>
  )
}

function EdicionDeCompetencia({ competencia, escalas, onClose, onGuardado, onRecargar }: InternoProps) {
  const idBase = useId()
  const formId = `${idBase}-formulario`
  const actualizar = useActualizarCompetencia(competencia.id, competencia.version)
  const escalaActual = escalas.find((e) => e.id === competencia.escala?.tipo_grado_id) ?? escalas.find((e) => e.activo) ?? escalas[0]

  const form = useForm<SeccionCompetenciaValues>({
    resolver: zodResolver(seccionCompetenciaSchema),
    defaultValues: {
      nombre: competencia.nombre,
      descripcion: competencia.descripcion,
      tipo: competencia.tipo,
      tipoGradoId: escalaActual.id,
      escala: escalaActual.nombre,
      categorias: competencia.categorias ?? categoriasPorDefecto(escalas),
      grados: completar(
        competencia.grados.map((grado) => grado.descripcion),
        CANTIDAD.grados,
      ),
    },
  })
  const {
    handleSubmit,
    setError,
    formState: { errors },
  } = form
  const { problema, errorNombre, limpiar, manejar } = useErroresDeGuardado(setError)

  const onSubmit = (valores: SeccionCompetenciaValues) => {
    if (actualizar.isPending) return
    limpiar()
    actualizar.mutate(
      {
        nombre: valores.nombre.trim(),
        descripcion: valores.descripcion.trim(),
        tipo: valores.tipo,
        tipo_grado_id: valores.tipoGradoId,
        ...(valores.escala === 'Categorías' ? { categorias: valores.categorias.map((nombre) => nombre.trim()) } : {}),
        grados: valores.grados.map((texto) => texto.trim()),
      },
      { onSuccess: () => onGuardado(MENSAJES.competencia), onError: manejar },
    )
  }

  return (
    <Modal
      open
      size="lg"
      title={TITULOS.competencia}
      description={competencia.nombre}
      onClose={onClose}
      busy={actualizar.isPending}
      closeLabel={text.close}
      footer={<Pie formId={formId} guardando={actualizar.isPending} onClose={onClose} />}
    >
      <form id={formId} onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
        <AvisosDeGuardado problema={problema} hayErrores={Object.keys(errors).length > 0 || errorNombre !== undefined} onRecargar={onRecargar} />
        <CamposDeCompetencia
          form={form as unknown as UseFormReturn<ValoresDeCompetencia>}
          escalas={escalas}
          idBase={idBase}
          errorNombre={errorNombre}
        />
      </form>
    </Modal>
  )
}

function EdicionDeComportamientos({ competencia, escalas, onClose, onGuardado, onRecargar }: InternoProps) {
  const idBase = useId()
  const formId = `${idBase}-formulario`
  const actualizar = useActualizarComportamientos(competencia.id, competencia.version)
  const escala = escalas.find((e) => e.id === competencia.escala?.tipo_grado_id)
  const etiquetas = competencia.grados.length === CANTIDAD.grados
    ? competencia.grados.map((grado) => grado.etiqueta)
    : escala
      ? etiquetasDeEscala(escala, competencia.categorias)
      : []

  const form = useForm<SeccionComportamientosValues>({
    resolver: zodResolver(seccionComportamientosSchema),
    defaultValues: {
      comportamientos: Array.from({ length: CANTIDAD.grados }, (_, indice) =>
        completar(competencia.grados[indice]?.comportamientos ?? [], CANTIDAD.comportamientosPorGrado),
      ),
    },
  })
  const {
    handleSubmit,
    setError,
    formState: { errors },
  } = form
  const { problema, limpiar, manejar } = useErroresDeGuardado(setError)

  const onSubmit = (valores: SeccionComportamientosValues) => {
    if (actualizar.isPending) return
    limpiar()
    actualizar.mutate(
      valores.comportamientos.map((grado) => grado.map((texto) => texto.trim())),
      { onSuccess: () => onGuardado(MENSAJES.comportamientos), onError: manejar },
    )
  }

  return (
    <Modal
      open
      size="lg"
      title={TITULOS.comportamientos}
      description={competencia.nombre}
      onClose={onClose}
      busy={actualizar.isPending}
      closeLabel={text.close}
      footer={<Pie formId={formId} guardando={actualizar.isPending} onClose={onClose} />}
    >
      <form id={formId} onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
        <AvisosDeGuardado problema={problema} hayErrores={Object.keys(errors).length > 0} onRecargar={onRecargar} />
        <CamposDeComportamientos form={form as unknown as UseFormReturn<ValoresDeComportamientos>} etiquetas={etiquetas} idBase={idBase} />
      </form>
    </Modal>
  )
}

function EdicionDePreguntas({ competencia, onClose, onGuardado, onRecargar }: InternoProps) {
  const idBase = useId()
  const formId = `${idBase}-formulario`
  const actualizar = useActualizarPreguntas(competencia.id, competencia.version)

  const form = useForm<SeccionPreguntasValues>({
    resolver: zodResolver(seccionPreguntasSchema),
    defaultValues: { preguntas: completar(competencia.preguntas, CANTIDAD.preguntas) },
  })
  const {
    handleSubmit,
    setError,
    formState: { errors },
  } = form
  const { problema, limpiar, manejar } = useErroresDeGuardado(setError)

  const onSubmit = (valores: SeccionPreguntasValues) => {
    if (actualizar.isPending) return
    limpiar()
    actualizar.mutate(
      valores.preguntas.map((texto) => texto.trim()),
      { onSuccess: () => onGuardado(MENSAJES.preguntas), onError: manejar },
    )
  }

  return (
    <Modal
      open
      size="lg"
      title={TITULOS.preguntas}
      description={competencia.nombre}
      onClose={onClose}
      busy={actualizar.isPending}
      closeLabel={text.close}
      footer={<Pie formId={formId} guardando={actualizar.isPending} onClose={onClose} />}
    >
      <form id={formId} onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
        <AvisosDeGuardado problema={problema} hayErrores={Object.keys(errors).length > 0} onRecargar={onRecargar} />
        <CamposDePreguntas form={form as unknown as UseFormReturn<ValoresDePreguntas>} idBase={idBase} />
      </form>
    </Modal>
  )
}

interface FormularioSeccionProps {
  /** Id de la competencia; `null` mantiene cerrado el formulario. */
  competenciaId: number | null
  seccion: SeccionEditable
  onClose: () => void
  /** Se llama con el mensaje del aviso cuando la sección se guardó. */
  onGuardado: (mensaje: string) => void
}

/**
 * Edita una sola sección de la competencia (competencia y grados, comportamientos o preguntas). Parte siempre de
 * una lectura fresca del detalle, con la versión que se enviará al guardar; si la competencia cambió mientras
 * se editaba, el servidor responde 409 y el formulario ofrece recargarla.
 */
export function FormularioSeccion({ competenciaId, seccion, onClose, onGuardado }: FormularioSeccionProps) {
  const abierto = competenciaId !== null
  const detalle = useCompetencia(competenciaId ?? 0, abierto, true)
  const escalas = useEscalas()

  if (!abierto) return null

  const cargando = detalle.isPending || escalas.isPending || (detalle.isFetching && !detalle.isFetchedAfterMount)

  if (cargando) {
    return (
      <Modal open size="lg" title={TITULOS[seccion]} onClose={onClose} closeLabel={text.close}>
        <div role="status" aria-label={text.loading} className="flex flex-col gap-3">
          <Skeleton className="h-10" />
          <Skeleton className="h-24" />
          <Skeleton className="h-40" />
        </div>
      </Modal>
    )
  }

  if (detalle.isError || escalas.isError) {
    const noExiste = detalle.isError && toApiError(detalle.error).status === 404
    return (
      <Modal open size="lg" title={TITULOS[seccion]} onClose={onClose} closeLabel={text.close}>
        {noExiste ? (
          <div role="alert" className="flex flex-col gap-2">
            <h3 className="text-base font-bold">{text.notFoundTitle}</h3>
            <p className="text-sm text-muted">{text.notFoundDescription}</p>
          </div>
        ) : (
          <ErrorState
            title={text.loadErrorTitle}
            description={text.loadErrorDescription}
            retryLabel={competenciasText.list.retry}
            onRetry={() => {
              void detalle.refetch()
              void escalas.refetch()
            }}
          />
        )}
      </Modal>
    )
  }

  // Al recargar tras un conflicto se lee de nuevo y `key` monta el formulario otra vez con la versión actual.
  const propiedades: InternoProps = {
    competencia: detalle.data,
    escalas: escalas.data,
    onClose,
    onGuardado,
    onRecargar: () => void detalle.refetch(),
  }
  const clave = detalle.dataUpdatedAt

  if (seccion === 'competencia') return <EdicionDeCompetencia key={clave} {...propiedades} />
  if (seccion === 'comportamientos') return <EdicionDeComportamientos key={clave} {...propiedades} />
  return <EdicionDePreguntas key={clave} {...propiedades} />
}
