import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField, type SelectOption } from '../../../../shared/ui/SelectField'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { TextField } from '../../../../shared/ui/TextField'
import { obtenerCategoria, type Categoria } from '../api'
import { useCompetenciasDisponibles } from '../hooks/useCategorias'
import { useGuardarCategoria } from '../hooks/useGuardarCategoria'
import { categoriasReconocimientoText } from '../text'
import {
  aDatosDeCategoria,
  categoriaSchema,
  LIMITE_DESCRIPCION,
  LIMITE_NOMBRE,
  problemaDeGuardado,
  valoresIniciales,
  type CategoriaFormValues,
  type ProblemaDeGuardado,
} from '../validation'
import { AdvertenciaCompetencia } from './AdvertenciaCompetencia'
import { ListaIndicadores } from './ListaIndicadores'
import { SelectorIcono } from './SelectorIcono'

const text = categoriasReconocimientoText.form
const FORM_ID = 'formulario-categoria-reconocimiento'
const SIN_PROBLEMA: ProblemaDeGuardado = { campos: {}, indicadores: {}, aviso: null }

interface CategoriaFormModalProps {
  /** Categoría con sus indicadores a editar; sin ella, alta. */
  categoria?: Categoria
  onCerrar: () => void
  onGuardado: (categoria: Categoria) => void
}

function Aviso({ aviso, onRecargar, recargando }: { aviso: ProblemaDeGuardado['aviso']; onRecargar: () => void; recargando: boolean }) {
  switch (aviso) {
    case 'conflicto':
      return (
        <Alert>
          <p>{text.conflict}</p>
          <Button variant="secondary" className="mt-2 min-h-10 px-3 text-sm" loading={recargando} onClick={onRecargar}>
            {text.reload}
          </Button>
        </Alert>
      )
    case 'noExiste':
      return <Alert>{text.notFound}</Alert>
    case 'red':
      return <Alert>{text.network}</Alert>
    case 'inesperado':
      return <Alert>{text.unexpected}</Alert>
    default:
      return null
  }
}

/** Crear y editar en un diálogo: errores por campo, valores conservados, guardado bloqueado mientras se envía. */
export function CategoriaFormModal({ categoria, onCerrar, onGuardado }: CategoriaFormModalProps) {
  const guardar = useGuardarCategoria()
  const disponibles = useCompetenciasDisponibles()
  const [version, setVersion] = useState(categoria?.updated_at)
  const [resultado, setResultado] = useState<{ problema: ProblemaDeGuardado; firma: string }>({ problema: SIN_PROBLEMA, firma: '' })
  const [descartando, setDescartando] = useState(false)
  const [recargando, setRecargando] = useState(false)
  const enviando = useRef(false)

  const {
    register,
    handleSubmit,
    control,
    reset,
    getValues,
    formState: { errors, isDirty },
  } = useForm<CategoriaFormValues>({
    resolver: zodResolver(categoriaSchema),
    defaultValues: valoresIniciales(categoria),
  })
  const valores = useWatch({ control })

  // Los errores de campo del servidor se retiran en cuanto se vuelve a escribir algo; el aviso general permanece.
  const problema: ProblemaDeGuardado =
    resultado.firma === JSON.stringify(valores)
      ? resultado.problema
      : { campos: {}, indicadores: {}, aviso: resultado.problema.aviso }
  const setProblema = (siguiente: ProblemaDeGuardado) => setResultado({ problema: siguiente, firma: JSON.stringify(getValues()) })

  const competenciaActual = categoria?.competencia ?? null
  const competenciaInactiva = categoria !== undefined && !categoria.competencia_activa && competenciaActual !== null

  const opciones = useMemo<SelectOption[]>(() => {
    const activas: SelectOption[] = (disponibles.data ?? []).map((c) => ({ value: String(c.id), label: c.nombre }))
    if (competenciaActual && !activas.some((o) => o.value === String(competenciaActual.id))) {
      return [
        { value: String(competenciaActual.id), label: categoriasReconocimientoText.competenciaInactivaOpcion(competenciaActual.nombre), disabled: true },
        ...activas,
      ]
    }
    return activas
  }, [disponibles.data, competenciaActual])

  const error = (campo: keyof ProblemaDeGuardado['campos'], zod?: string) => zod ?? problema.campos[campo]
  const hayErrores =
    Object.keys(errors).length > 0 || Object.values(problema.campos).some(Boolean) || Object.keys(problema.indicadores).length > 0

  const nombreLargo = String(valores.nombre ?? '').trim().length
  const descripcionLarga = String(valores.descripcion ?? '').trim().length
  const ocupado = guardar.isPending

  const intentarCerrar = () => {
    if (ocupado) return
    if (isDirty) setDescartando(true)
    else onCerrar()
  }

  const recargar = async () => {
    if (!categoria) return
    setRecargando(true)
    try {
      const vigente = await obtenerCategoria(categoria.id)
      reset(valoresIniciales(vigente))
      setVersion(vigente.updated_at)
      setProblema(SIN_PROBLEMA)
    } catch (e) {
      setProblema(problemaDeGuardado(e))
    } finally {
      setRecargando(false)
    }
  }

  const enviar = (formulario: CategoriaFormValues) => {
    if (enviando.current) return
    enviando.current = true
    setProblema(SIN_PROBLEMA)
    guardar.mutate(
      { datos: aDatosDeCategoria(formulario), fila: categoria && version ? { id: categoria.id, version } : undefined },
      {
        onSuccess: onGuardado,
        onError: (e) => setProblema(problemaDeGuardado(e)),
        onSettled: () => {
          enviando.current = false
        },
      },
    )
  }

  return (
    <>
      <Modal
        open
        size="lg"
        title={categoria ? text.editTitle : text.createTitle}
        description={categoria ? text.codeEdit(categoria.codigo) : text.codeCreate}
        busy={ocupado}
        closeLabel={text.close}
        onClose={intentarCerrar}
        footer={
          <>
            <Button variant="secondary" disabled={ocupado} onClick={intentarCerrar}>
              {text.cancel}
            </Button>
            <Button type="submit" form={FORM_ID} loading={ocupado}>
              {ocupado ? text.saving : categoria ? text.save : text.create}
            </Button>
          </>
        }
      >
        <form id={FORM_ID} noValidate aria-busy={ocupado} onSubmit={(evento) => void handleSubmit(enviar)(evento)} className="flex flex-col gap-4">
          {hayErrores && !ocupado && problema.aviso === null && <Alert>{text.errorsAlert}</Alert>}
          <Aviso aviso={problema.aviso} onRecargar={() => void recargar()} recargando={recargando} />

          <TextField
            id="categoria-nombre"
            label={text.nameLabel}
            error={error('nombre', errors.nombre?.message)}
            hint={`${text.nameHint} ${nombreLargo}/${LIMITE_NOMBRE}`}
            autoComplete="off"
            {...register('nombre')}
          />

          <TextareaField
            id="categoria-descripcion"
            label={text.descriptionLabel}
            error={error('descripcion', errors.descripcion?.message)}
            hint={text.descriptionHint}
            currentLength={descripcionLarga}
            maxLength={LIMITE_DESCRIPCION}
            {...register('descripcion')}
          />

          <div className="flex flex-col gap-1.5">
            <SelectField
              id="categoria-competencia"
              label={text.competenciaLabel}
              options={[{ value: '', label: text.competenciaPlaceholder }, ...opciones]}
              error={error('competencia_id', errors.competencia_id?.message)}
              {...register('competencia_id')}
            />
            {competenciaInactiva && (
              <div>
                <AdvertenciaCompetencia />
              </div>
            )}
            <p className="text-[13px] text-muted">{competenciaInactiva ? text.competenciaHintInactiva : text.competenciaHint}</p>
          </div>

          <div className="flex flex-col gap-1.5">
            <TextField
              id="categoria-puntuacion"
              label={text.pointsLabel}
              type="number"
              min={1}
              max={1000}
              step={1}
              inputMode="numeric"
              error={error('puntuacion_base', errors.puntuacion_base?.message)}
              {...register('puntuacion_base')}
            />
            <p className="text-[13px] text-muted">{text.pointsHint}</p>
          </div>

          <SelectorIcono
            registro={register('icono')}
            seleccionado={String(valores.icono ?? '')}
            error={error('icono', errors.icono?.message)}
          />

          <ListaIndicadores
            control={control}
            register={register}
            errors={errors}
            erroresDelServidor={problema.indicadores}
            errorDelGrupo={problema.campos.indicadores}
          />
        </form>
      </Modal>

      <ConfirmDialog
        open={descartando}
        title={categoriasReconocimientoText.discard.title}
        description={categoriasReconocimientoText.discard.description}
        confirmLabel={categoriasReconocimientoText.discard.discard}
        cancelLabel={categoriasReconocimientoText.discard.keep}
        onConfirm={onCerrar}
        onCancel={() => setDescartando(false)}
      />
    </>
  )
}
