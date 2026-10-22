import { useEffect, useRef } from 'react'
import { useFieldArray, type Control, type FieldErrors, type UseFormRegister } from 'react-hook-form'
import { Button } from '../../../../shared/ui/Button'
import { IconPlus, IconX } from '../../../../shared/ui/icons'
import { TextField } from '../../../../shared/ui/TextField'
import { categoriasReconocimientoText } from '../text'
import { MAX_INDICADORES, type CategoriaFormValues } from '../validation'

const text = categoriasReconocimientoText.form

interface ListaIndicadoresProps {
  control: Control<CategoriaFormValues>
  register: UseFormRegister<CategoriaFormValues>
  errors: FieldErrors<CategoriaFormValues>
  /** Mensajes del servidor por posición de indicador. */
  erroresDelServidor: Record<number, string>
  /** Error del servidor sobre el grupo (cantidad). */
  errorDelGrupo?: string
  disabled?: boolean
}

/** De 1 a 5 indicadores: «Agregar indicador» se deshabilita al llegar a 5 y «Eliminar» cuando queda 1. */
export function ListaIndicadores({ control, register, errors, erroresDelServidor, errorDelGrupo, disabled = false }: ListaIndicadoresProps) {
  const { fields, append, remove } = useFieldArray({ control, name: 'indicadores' })
  const enfocarUltimo = useRef(false)
  const campos = useRef<(HTMLElement | null)[]>([])

  // Tras agregar, el foco pasa al campo nuevo.
  useEffect(() => {
    if (enfocarUltimo.current) {
      enfocarUltimo.current = false
      campos.current[fields.length - 1]?.focus()
    }
  }, [fields.length])

  const errorDelGrupoZod = errors.indicadores?.root?.message ?? (typeof errors.indicadores?.message === 'string' ? errors.indicadores.message : undefined)
  const errorGeneral = errorDelGrupoZod ?? errorDelGrupo
  const enElMinimo = fields.length <= 1
  const enElMaximo = fields.length >= MAX_INDICADORES

  return (
    <fieldset disabled={disabled} className="m-0 flex min-w-0 flex-col gap-3 border-0 p-0">
      <legend className="p-0 text-sm font-semibold">{text.indicatorsLegend}</legend>
      <p id="categoria-indicadores-ayuda" className="text-[13px] text-muted">
        {text.indicatorsHint}
      </p>
      {errorGeneral && (
        <p role="alert" className="text-[13px] text-danger">
          {errorGeneral}
        </p>
      )}
      <ul className="m-0 flex list-none flex-col gap-2.5 p-0">
        {fields.map((campo, posicion) => {
          const { ref, ...resto } = register(`indicadores.${posicion}.texto`)
          const error = errors.indicadores?.[posicion]?.texto?.message ?? erroresDelServidor[posicion]
          return (
            <li key={campo.id} className="flex items-start gap-2">
              <span aria-hidden="true" className="mt-3 w-5 flex-none text-center text-sm font-semibold text-muted">
                {posicion + 1}
              </span>
              <div className="min-w-0 flex-1">
                <TextField
                  id={`categoria-indicador-${posicion + 1}`}
                  label={text.indicatorLabel(posicion + 1)}
                  hideLabel
                  error={error}
                  autoComplete="off"
                  {...resto}
                  ref={(elemento) => {
                    ref(elemento)
                    campos.current[posicion] = elemento
                  }}
                />
              </div>
              <button
                type="button"
                aria-label={text.indicatorRemove(posicion + 1)}
                title={enElMinimo ? text.indicatorRemoveMinimum : text.indicatorRemoveTip}
                disabled={enElMinimo}
                onClick={() => remove(posicion)}
                className="inline-flex size-11 flex-none cursor-pointer items-center justify-center rounded-lg border border-border bg-transparent text-text hover:bg-soft disabled:cursor-not-allowed disabled:opacity-40"
              >
                <IconX size={18} aria-hidden="true" />
              </button>
            </li>
          )
        })}
      </ul>
      <div className="flex flex-wrap items-center gap-3">
        <Button
          variant="secondary"
          className="min-h-10 px-3 text-sm"
          disabled={enElMaximo}
          onClick={() => {
            enfocarUltimo.current = true
            append({ texto: '' })
          }}
        >
          <IconPlus size={16} aria-hidden="true" />
          {text.indicatorAdd}
        </Button>
        <span aria-live="polite" className="text-[13px] text-muted">
          {text.indicatorCount(fields.length)}
        </span>
      </div>
    </fieldset>
  )
}
