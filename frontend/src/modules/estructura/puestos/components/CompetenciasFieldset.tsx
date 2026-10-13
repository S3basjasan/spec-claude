import { Controller, useFieldArray, useFormContext, useWatch } from 'react-hook-form'
import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import { Combobox } from '../../../../shared/ui/Combobox'
import { IconPlus, IconX } from '../../../../shared/ui/icons'
import { SelectField } from '../../../../shared/ui/SelectField'
import type { CompetenciaAsignada, CompetenciaDisponible } from '../api'
import { puestosText } from '../text'
import { LIMITES, type PuestoFormValues } from '../validation'
import { mensajeDeLista } from './mensajeDeLista'

const text = puestosText.form

interface CompetenciasFieldsetProps {
  /** Competencias activas que pueden agregarse (con sus grados). */
  disponibles: CompetenciaDisponible[]
  /** Competencias que el puesto ya tiene (al editar): sirven para reconocer las inactivas, cuyo grado queda bloqueado. */
  asignadas?: CompetenciaAsignada[]
}

const botonQuitar =
  'flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-border bg-surface text-danger hover:bg-soft'

/**
 * Competencias del puesto. Solo se ofrecen competencias activas que aún no están en el puesto; el grado ofrece los de
 * la competencia elegida. Una competencia inactiva ya asignada solo puede mantenerse (con su grado) o quitarse.
 */
export function CompetenciasFieldset({ disponibles, asignadas = [] }: CompetenciasFieldsetProps) {
  const {
    control,
    register,
    setValue,
    formState: { errors },
  } = useFormContext<PuestoFormValues>()
  const { fields, append, remove } = useFieldArray({ control, name: 'competencias' })
  const valores = useWatch({ control, name: 'competencias' })

  const elegidas = new Set((valores ?? []).map((v) => v?.competencia_id).filter((id): id is number => typeof id === 'number'))
  const errorLista = mensajeDeLista(errors.competencias)
  const lleno = fields.length >= LIMITES.elementos

  return (
    <fieldset className="flex min-w-0 flex-col gap-3 rounded-lg border border-border p-4">
      <legend className="px-1 text-base font-bold">{text.competenciesLegend}</legend>
      {errorLista && (
        <p role="alert" className="text-[13px] text-danger">
          {errorLista}
        </p>
      )}

      {fields.map((campo, indice) => {
        const id = valores?.[indice]?.competencia_id
        const asignada = asignadas.find((a) => a.competencia_id === id)

        if (asignada?.estado === 'Inactivo') {
          const grado = valores?.[indice]?.grado_requerido ?? asignada.grado_requerido
          return (
            <div key={campo.id} className="flex flex-col gap-2.5 rounded-[10px] border border-border bg-bg p-3.5">
              <div className="flex flex-wrap items-start gap-2">
                <div className="min-w-0 flex-[1_1_260px]">
                  <p className="text-sm font-semibold">{text.competency(indice + 1)}</p>
                  <p className="flex min-h-11 items-center gap-2 text-[15px] font-semibold">
                    {asignada.nombre}
                    <Badge variant="neutral">{puestosText.detail.inactiveCompetency}</Badge>
                  </p>
                </div>
                <div className="w-37.5">
                  <p className="text-sm font-semibold">{text.requiredGrade}</p>
                  <p className="flex min-h-11 items-center">
                    <span
                      aria-label={puestosText.detail.gradeLabel(grado)}
                      className="inline-flex h-7 min-w-8 items-center justify-center rounded-md bg-accent px-2 text-sm font-bold text-onaccent"
                    >
                      {grado}
                    </span>
                  </p>
                </div>
                <button type="button" aria-label={text.removeCompetency(indice + 1)} onClick={() => remove(indice)} className={`${botonQuitar} mt-6.5`}>
                  <IconX size={18} />
                </button>
              </div>
              <p className="text-[13px] text-muted">
                {asignada.codigo} · {puestosText.competencyTypes[asignada.tipo]} · {text.lockedGrade}
              </p>
            </div>
          )
        }

        const info = disponibles.find((d) => d.competencia_id === id)

        return (
          <div key={campo.id} className="flex flex-col gap-2.5 rounded-[10px] border border-border p-3.5">
            <div className="flex flex-wrap items-start gap-2">
              <div className="min-w-0 flex-[1_1_260px]">
                <Controller
                  control={control}
                  name={`competencias.${indice}.competencia_id`}
                  render={({ field }) => (
                    <Combobox
                      id={`competencia-${indice + 1}`}
                      label={text.competency(indice + 1)}
                      placeholder={text.competencyPlaceholder}
                      emptyText={text.competencyEmpty}
                      options={disponibles
                        .filter((d) => d.competencia_id === id || !elegidas.has(d.competencia_id))
                        .map((d) => ({ value: String(d.competencia_id), label: d.nombre, description: d.codigo }))}
                      value={field.value === undefined ? null : String(field.value)}
                      onChange={(valor) => {
                        field.onChange(valor === null ? undefined : Number(valor))
                        // El grado depende de la competencia: al cambiarla se vuelve a elegir.
                        setValue(`competencias.${indice}.grado_requerido`, '')
                      }}
                      error={errors.competencias?.[indice]?.competencia_id?.message}
                    />
                  )}
                />
              </div>
              <div className="w-37.5">
                <SelectField
                  id={`grado-${indice + 1}`}
                  label={text.requiredGrade}
                  options={[
                    { value: '', label: text.gradePlaceholder },
                    ...(info?.grados ?? []).map((g) => ({ value: g.grado, label: g.grado })),
                  ]}
                  error={errors.competencias?.[indice]?.grado_requerido?.message}
                  disabled={!info}
                  {...register(`competencias.${indice}.grado_requerido`)}
                />
              </div>
              <button type="button" aria-label={text.removeCompetency(indice + 1)} onClick={() => remove(indice)} className={`${botonQuitar} mt-6.5`}>
                <IconX size={18} />
              </button>
            </div>
            {info && (
              <p className="flex flex-wrap items-center gap-1.5 text-[13px] text-muted">
                {info.codigo} · {puestosText.competencyTypes[info.tipo]} · <Badge>{puestosText.statuses.Activo}</Badge>
              </p>
            )}
          </div>
        )
      })}

      <div className="flex flex-wrap items-center gap-3">
        <Button
          variant="secondary"
          disabled={lleno}
          onClick={() => append({ competencia_id: undefined as unknown as number, grado_requerido: '' })}
        >
          <IconPlus size={18} />
          {text.addCompetency}
        </Button>
        {lleno && <span className="text-[13px] text-muted">{text.maxReached}</span>}
      </div>
    </fieldset>
  )
}
