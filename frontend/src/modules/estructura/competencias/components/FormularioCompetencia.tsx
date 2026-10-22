import { zodResolver } from '@hookform/resolvers/zod'
import { useId } from 'react'
import { useForm, useWatch, type UseFormReturn } from 'react-hook-form'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Modal } from '../../../../shared/ui/Modal'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { CANTIDAD, type Escala } from '../api'
import { aDatosDeCreacion } from '../datos'
import { categoriasPorDefecto, etiquetasDeEscala } from '../escalas'
import { useEscalas } from '../hooks/useEscalas'
import { useErroresDeGuardado } from '../hooks/useErroresDeGuardado'
import { useCrearCompetencia } from '../hooks/useGuardarCompetencia'
import { competenciasText } from '../text'
import { competenciaCompletaSchema, type CompetenciaCompletaValues } from '../validation'
import {
  CamposDeComportamientos,
  CamposDeCompetencia,
  CamposDePreguntas,
  type ValoresDeComportamientos,
  type ValoresDeCompetencia,
  type ValoresDePreguntas,
} from './camposDeFormulario'

const text = competenciasText.form

const vacios = (cantidad: number) => Array.from({ length: cantidad }, () => '')

interface FormularioCompetenciaProps {
  open: boolean
  onClose: () => void
  /** Se llama con el mensaje del aviso cuando la competencia se creó. */
  onCreada: (mensaje: string) => void
}

function Formulario({ escalas, onClose, onCreada }: { escalas: Escala[]; onClose: () => void; onCreada: (mensaje: string) => void }) {
  const idBase = useId()
  const formId = `${idBase}-formulario`
  const crear = useCrearCompetencia()
  const escalaInicial = escalas.find((escala) => escala.activo) ?? escalas[0]

  const form = useForm<CompetenciaCompletaValues>({
    resolver: zodResolver(competenciaCompletaSchema),
    defaultValues: {
      nombre: '',
      descripcion: '',
      // El tipo se elige: empieza vacío para que la validación lo exija.
      tipo: '' as CompetenciaCompletaValues['tipo'],
      tipoGradoId: escalaInicial.id,
      escala: escalaInicial.nombre,
      categorias: categoriasPorDefecto(escalas),
      grados: vacios(CANTIDAD.grados),
      comportamientos: Array.from({ length: CANTIDAD.grados }, () => vacios(CANTIDAD.comportamientosPorGrado)),
      preguntas: vacios(CANTIDAD.preguntas),
    },
  })
  const {
    handleSubmit,
    setError,
    control,
    formState: { errors },
  } = form
  const { problema, errorNombre, limpiar, manejar } = useErroresDeGuardado(setError)

  const tipoGradoId = useWatch({ control, name: 'tipoGradoId' })
  const categorias = useWatch({ control, name: 'categorias' }) ?? []
  const escala = escalas.find((e) => e.id === tipoGradoId)
  const etiquetas = escala ? etiquetasDeEscala(escala, escala.nombre === 'Categorías' ? categorias.map((c) => c.trim()) : null) : []

  const onSubmit = (valores: CompetenciaCompletaValues) => {
    if (crear.isPending) return
    limpiar()
    crear.mutate(aDatosDeCreacion(valores), {
      onSuccess: () => onCreada(competenciasText.notices.created),
      onError: manejar,
    })
  }

  const hayErrores = Object.keys(errors).length > 0 || errorNombre !== undefined

  return (
    <Modal
      open
      size="xl"
      title={text.newTitle}
      onClose={onClose}
      busy={crear.isPending}
      closeLabel={text.close}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={crear.isPending}>
            {text.cancel}
          </Button>
          <Button type="submit" form={formId} loading={crear.isPending}>
            {crear.isPending ? text.saving : text.save}
          </Button>
        </>
      }
    >
      <form id={formId} onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
        <p className="text-[13px] text-muted">{text.help}</p>
        {hayErrores && <Alert>{text.reviewFields}</Alert>}
        {problema === 'red' && <Alert>{text.networkError}</Alert>}
        {problema === 'inesperado' && <Alert>{text.unexpectedError}</Alert>}

        <CamposDeCompetencia
          form={form as unknown as UseFormReturn<ValoresDeCompetencia>}
          escalas={escalas}
          idBase={idBase}
          errorNombre={errorNombre}
        />
        <CamposDeComportamientos form={form as unknown as UseFormReturn<ValoresDeComportamientos>} etiquetas={etiquetas} idBase={idBase} />
        <CamposDePreguntas form={form as unknown as UseFormReturn<ValoresDePreguntas>} idBase={idBase} />
      </form>
    </Modal>
  )
}

/** Formulario "Nueva competencia": crea la competencia completa en una sola operación. */
export function FormularioCompetencia({ open, onClose, onCreada }: FormularioCompetenciaProps) {
  const escalas = useEscalas()

  if (!open) return null

  if (escalas.isPending || escalas.isError) {
    return (
      <Modal open size="xl" title={text.newTitle} onClose={onClose} closeLabel={text.close}>
        {escalas.isPending ? (
          <div role="status" aria-label={text.loading} className="flex flex-col gap-3">
            <Skeleton className="h-10" />
            <Skeleton className="h-24" />
            <Skeleton className="h-40" />
          </div>
        ) : (
          <ErrorState
            title={text.loadErrorTitle}
            description={text.loadErrorDescription}
            retryLabel={competenciasText.list.retry}
            onRetry={() => void escalas.refetch()}
          />
        )}
      </Modal>
    )
  }

  return <Formulario escalas={escalas.data} onClose={onClose} onCreada={onCreada} />
}
