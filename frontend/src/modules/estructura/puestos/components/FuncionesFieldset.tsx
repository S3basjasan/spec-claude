import { useState } from 'react'
import { useFieldArray, useFormContext, useWatch } from 'react-hook-form'
import { Button } from '../../../../shared/ui/Button'
import { IconPlus } from '../../../../shared/ui/icons'
import { puestosText } from '../text'
import { LIMITES, type PuestoFormValues } from '../validation'
import { FilaTipoTexto } from './FilaTipoTexto'
import { mensajeDeLista } from './mensajeDeLista'

const text = puestosText.form

const TIPOS = [
  { value: 'Esencial', label: puestosText.functionTypes.Esencial },
  { value: 'Complementaria', label: puestosText.functionTypes.Complementaria },
]

/** Funciones del puesto: lista ordenada (numerada desde 1) que se puede agregar, quitar y reordenar. */
export function FuncionesFieldset() {
  const {
    control,
    register,
    formState: { errors },
  } = useFormContext<PuestoFormValues>()
  const { fields, append, remove, move } = useFieldArray({ control, name: 'funciones' })
  const valores = useWatch({ control, name: 'funciones' })
  const [anuncio, setAnuncio] = useState('')

  const errorLista = mensajeDeLista(errors.funciones)
  const lleno = fields.length >= LIMITES.elementos

  const mover = (desde: number, hasta: number) => {
    move(desde, hasta)
    setAnuncio(text.moved(hasta + 1, fields.length))
  }

  return (
    <fieldset className="flex min-w-0 flex-col gap-3 rounded-lg border border-border p-4">
      <legend className="px-1 text-base font-bold">{text.functionsLegend}</legend>
      {errorLista && (
        <p role="alert" className="text-[13px] text-danger">
          {errorLista}
        </p>
      )}

      {fields.map((campo, indice) => (
        <FilaTipoTexto
          key={campo.id}
          id={`funcion-${indice + 1}`}
          numero={indice + 1}
          tipoLabel={text.functionType(indice + 1)}
          textoLabel={text.functionText(indice + 1)}
          tipoOptions={TIPOS}
          tipoProps={register(`funciones.${indice}.tipo`)}
          textoProps={register(`funciones.${indice}.descripcion`)}
          longitud={valores?.[indice]?.descripcion?.length ?? 0}
          maximo={LIMITES.texto}
          errorTipo={errors.funciones?.[indice]?.tipo?.message}
          errorTexto={errors.funciones?.[indice]?.descripcion?.message}
          quitarLabel={text.removeFunction(indice + 1)}
          onQuitar={() => remove(indice)}
          mover={{
            subirLabel: text.moveUp(indice + 1),
            bajarLabel: text.moveDown(indice + 1),
            puedeSubir: indice > 0,
            puedeBajar: indice < fields.length - 1,
            onSubir: () => mover(indice, indice - 1),
            onBajar: () => mover(indice, indice + 1),
          }}
        />
      ))}

      <p role="status" aria-live="polite" className="min-h-4 text-[13px] text-muted">
        {anuncio}
      </p>
      <div className="flex flex-wrap items-center gap-3">
        <Button variant="secondary" disabled={lleno} onClick={() => append({ tipo: 'Complementaria', descripcion: '' })}>
          <IconPlus size={18} />
          {text.addFunction}
        </Button>
        {lleno && <span className="text-[13px] text-muted">{text.maxReached}</span>}
      </div>
    </fieldset>
  )
}
