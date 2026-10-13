import { useFieldArray, useFormContext, useWatch } from 'react-hook-form'
import { Button } from '../../../../shared/ui/Button'
import { IconPlus } from '../../../../shared/ui/icons'
import { puestosText } from '../text'
import { LIMITES, type PuestoFormValues } from '../validation'
import { FilaTipoTexto } from './FilaTipoTexto'
import { mensajeDeLista } from './mensajeDeLista'

const text = puestosText.form

const TIPOS = [
  { value: 'Formacion', label: puestosText.requirementTypes.Formacion },
  { value: 'Experiencia', label: puestosText.requirementTypes.Experiencia },
  { value: 'Capacitacion', label: puestosText.requirementTypes.Capacitacion },
]

/** Requisitos del puesto: al menos uno de Formación y uno de Experiencia; Capacitación es opcional. */
export function RequisitosFieldset() {
  const {
    control,
    register,
    formState: { errors },
  } = useFormContext<PuestoFormValues>()
  const { fields, append, remove } = useFieldArray({ control, name: 'requisitos' })
  const valores = useWatch({ control, name: 'requisitos' })

  const errorLista = mensajeDeLista(errors.requisitos)
  const lleno = fields.length >= LIMITES.elementos

  return (
    <fieldset className="flex min-w-0 flex-col gap-3 rounded-lg border border-border p-4">
      <legend className="px-1 text-base font-bold">{text.requirementsLegend}</legend>
      {errorLista && (
        <p role="alert" className="text-[13px] text-danger">
          {errorLista}
        </p>
      )}

      {fields.map((campo, indice) => (
        <FilaTipoTexto
          key={campo.id}
          id={`requisito-${indice + 1}`}
          tipoLabel={text.requirementType(indice + 1)}
          textoLabel={text.requirementText(indice + 1)}
          tipoOptions={TIPOS}
          tipoProps={register(`requisitos.${indice}.tipo`)}
          textoProps={register(`requisitos.${indice}.descripcion`)}
          longitud={valores?.[indice]?.descripcion?.length ?? 0}
          maximo={LIMITES.texto}
          errorTipo={errors.requisitos?.[indice]?.tipo?.message}
          errorTexto={errors.requisitos?.[indice]?.descripcion?.message}
          quitarLabel={text.removeRequirement(indice + 1)}
          onQuitar={() => remove(indice)}
        />
      ))}

      <div className="flex flex-wrap items-center gap-3">
        <Button variant="secondary" disabled={lleno} onClick={() => append({ tipo: 'Capacitacion', descripcion: '' })}>
          <IconPlus size={18} />
          {text.addRequirement}
        </Button>
        {lleno && <span className="text-[13px] text-muted">{text.maxReached}</span>}
      </div>
    </fieldset>
  )
}
