import { useWatch, type FieldValues, type Path, type UseFormReturn } from 'react-hook-form'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { TextField } from '../../../../shared/ui/TextField'
import { SelectField } from '../../../../shared/ui/SelectField'
import type { Escala } from '../api'
import { etiquetasDeEscala } from '../escalas'
import { conErrorEn, primerMensaje } from '../erroresDeFormulario'
import { competenciasText } from '../text'
import { LIMITES } from '../validation'
import { SelectorDeEscala } from './SelectorDeEscala'

const text = competenciasText.form

/** Campos comunes del formulario de la competencia (creación y edición de la sección Competencia). */
export interface ValoresDeCompetencia extends FieldValues {
  nombre: string
  descripcion: string
  tipo: 'Cardinal' | 'Especifica'
  tipoGradoId: number
  escala: string
  categorias: string[]
  grados: string[]
}

export interface ValoresDeComportamientos extends FieldValues {
  comportamientos: string[][]
}

export interface ValoresDePreguntas extends FieldValues {
  preguntas: string[]
}

interface CamposDeCompetenciaProps {
  form: UseFormReturn<ValoresDeCompetencia>
  escalas: Escala[]
  /** Prefijo de los ids de los campos (único por formulario abierto). */
  idBase: string
  /** Mensaje del servidor para el nombre (duplicado), que el esquema local no conoce. */
  errorNombre?: string
}

/** Nombre y tipo, descripción, escala (con nombres de categoría) y los cuatro grados. */
export function CamposDeCompetencia({ form, escalas, idBase, errorNombre }: CamposDeCompetenciaProps) {
  const {
    register,
    setValue,
    control,
    formState: { errors },
  } = form
  const nombre = useWatch({ control, name: 'nombre' }) ?? ''
  const descripcion = useWatch({ control, name: 'descripcion' }) ?? ''
  const tipoGradoId = useWatch({ control, name: 'tipoGradoId' })
  const categorias = useWatch({ control, name: 'categorias' }) ?? []
  const grados = useWatch({ control, name: 'grados' }) ?? []
  const escalaElegida = escalas.find((escala) => escala.id === tipoGradoId)
  const etiquetas = escalaElegida ? etiquetasDeEscala(escalaElegida, escalaElegida.nombre === 'Categorías' ? categorias.map((c) => c.trim()) : null) : []
  const mensajeCategorias = primerMensaje(errors.categorias)
  const mensajeGrados = primerMensaje(errors.grados)

  return (
    <>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <TextField
          id={`${idBase}-nombre`}
          label={text.name}
          placeholder={text.namePlaceholder}
          maxLength={LIMITES.nombre}
          hint={`${nombre.length}/${LIMITES.nombre}`}
          error={errorNombre ?? errors.nombre?.message}
          {...register('nombre')}
        />
        <SelectField
          id={`${idBase}-tipo`}
          label={text.type}
          error={errors.tipo?.message}
          options={[
            { value: '', label: text.typePlaceholder },
            { value: 'Cardinal', label: competenciasText.types.Cardinal },
            { value: 'Especifica', label: competenciasText.types.Especifica },
          ]}
          {...register('tipo')}
        />
      </div>

      <TextareaField
        id={`${idBase}-descripcion`}
        label={text.description}
        placeholder={text.descriptionPlaceholder}
        maxLength={LIMITES.texto}
        currentLength={descripcion.length}
        error={errors.descripcion?.message}
        {...register('descripcion')}
      />

      <SelectorDeEscala
        escalas={escalas}
        valor={Number.isFinite(tipoGradoId) ? tipoGradoId : null}
        error={errors.tipoGradoId?.message}
        onElegir={(escala) => {
          setValue('tipoGradoId', escala.id, { shouldValidate: false })
          setValue('escala', escala.nombre, { shouldValidate: false })
        }}
        categorias={
          <div className="mt-1 flex flex-col gap-2">
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
              {[0, 1, 2, 3].map((indice) => (
                <TextField
                  key={indice}
                  id={`${idBase}-categoria-${indice}`}
                  label={text.categoryLabel(indice + 1)}
                  hideLabel
                  placeholder={text.categoryLabel(indice + 1)}
                  maxLength={LIMITES.categoria}
                  aria-invalid={conErrorEn(errors, `categorias.${indice}`) || undefined}
                  {...register(`categorias.${indice}` as Path<ValoresDeCompetencia>)}
                />
              ))}
            </div>
            {mensajeCategorias && (
              <p role="alert" className="text-[13px] text-danger">
                {mensajeCategorias}
              </p>
            )}
          </div>
        }
      />

      <fieldset className="flex flex-col gap-3">
        <legend className="mb-1 text-[15px] font-semibold">{text.gradesLegend}</legend>
        {[0, 1, 2, 3].map((indice) => (
          <TextareaField
            key={indice}
            id={`${idBase}-grado-${indice}`}
            label={etiquetas[indice] ?? text.categoryLabel(indice + 1)}
            placeholder={text.gradePlaceholder}
            maxLength={LIMITES.texto}
            currentLength={grados[indice]?.length ?? 0}
            aria-invalid={conErrorEn(errors, `grados.${indice}`) || undefined}
            rows={2}
            {...register(`grados.${indice}` as Path<ValoresDeCompetencia>)}
          />
        ))}
        {mensajeGrados && (
          <p role="alert" className="text-[13px] text-danger">
            {mensajeGrados}
          </p>
        )}
      </fieldset>
    </>
  )
}

interface CamposDeComportamientosProps {
  form: UseFormReturn<ValoresDeComportamientos>
  /** Nombres de los cuatro grados con la escala vigente. */
  etiquetas: string[]
  idBase: string
}

/** Cuatro comportamientos por cada uno de los cuatro grados. */
export function CamposDeComportamientos({ form, etiquetas, idBase }: CamposDeComportamientosProps) {
  const {
    register,
    formState: { errors },
  } = form
  const mensaje = primerMensaje(errors.comportamientos)

  return (
    <fieldset className="flex flex-col gap-4">
      <legend className="mb-1 text-[15px] font-semibold">{text.behaviorsLegend}</legend>
      {[0, 1, 2, 3].map((grado) => (
        <div key={grado} className="flex flex-col gap-1.5">
          <span className="text-sm font-semibold text-muted">{etiquetas[grado] ?? text.categoryLabel(grado + 1)}</span>
          {[0, 1, 2, 3].map((numero) => (
            <TextField
              key={numero}
              id={`${idBase}-comportamiento-${grado}-${numero}`}
              label={`${etiquetas[grado] ?? ''} · ${text.behaviorLabel(numero + 1)}`}
              hideLabel
              placeholder={text.behaviorLabel(numero + 1)}
              maxLength={LIMITES.comportamiento}
              aria-invalid={conErrorEn(errors, `comportamientos.${grado}.${numero}`) || undefined}
              {...register(`comportamientos.${grado}.${numero}` as Path<ValoresDeComportamientos>)}
            />
          ))}
        </div>
      ))}
      {mensaje && (
        <p role="alert" className="text-[13px] text-danger">
          {mensaje}
        </p>
      )}
    </fieldset>
  )
}

interface CamposDePreguntasProps {
  form: UseFormReturn<ValoresDePreguntas>
  idBase: string
}

/** Las cuatro preguntas de la competencia, numeradas. */
export function CamposDePreguntas({ form, idBase }: CamposDePreguntasProps) {
  const {
    register,
    formState: { errors },
  } = form
  const mensaje = primerMensaje(errors.preguntas)

  return (
    <fieldset className="flex flex-col gap-2">
      <legend className="mb-1 text-[15px] font-semibold">{text.questionsLegend}</legend>
      {[0, 1, 2, 3].map((indice) => (
        <TextField
          key={indice}
          id={`${idBase}-pregunta-${indice}`}
          label={text.questionLabel(indice + 1)}
          hideLabel
          placeholder={text.questionLabel(indice + 1)}
          maxLength={LIMITES.texto}
          aria-invalid={conErrorEn(errors, `preguntas.${indice}`) || undefined}
          {...register(`preguntas.${indice}` as Path<ValoresDePreguntas>)}
        />
      ))}
      {mensaje && (
        <p role="alert" className="text-[13px] text-danger">
          {mensaje}
        </p>
      )}
    </fieldset>
  )
}
