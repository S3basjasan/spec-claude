import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Combobox } from '../../../../shared/ui/Combobox'
import { SelectField } from '../../../../shared/ui/SelectField'
import type { Alcance, OpcionesDnf } from '../api'
import { dnfText } from '../text'
import { seleccionSchema, type Seleccion } from '../validation'

const text = dnfText.admin.paso1

export interface ErroresDelServidor {
  colaborador_id?: string
  departamento_id?: string
  general?: string
}

interface PasoSeleccionProps {
  opciones: OpcionesDnf
  enviando: boolean
  errores: ErroresDelServidor
  onSubmit: (seleccion: Seleccion) => void
}

/** Paso 1 del asistente: alcance (un colaborador o un departamento) y su selector con búsqueda. */
export function PasoSeleccion({ opciones, enviando, errores, onSubmit }: PasoSeleccionProps) {
  const {
    control,
    handleSubmit,
    setValue,
    register,
    formState: { errors },
  } = useForm<Seleccion>({
    resolver: zodResolver(seleccionSchema),
    defaultValues: { alcance: 'Individual', colaborador_id: null, departamento_id: null },
  })
  const alcance = useWatch({ control, name: 'alcance' })
  const colaboradorId = useWatch({ control, name: 'colaborador_id' })
  const departamentoId = useWatch({ control, name: 'departamento_id' })
  const colaborador = opciones.colaboradores.find((c) => c.id === colaboradorId)
  const departamento = opciones.departamentos.find((d) => d.id === departamentoId)

  const cambiarAlcance = (nuevo: Alcance) => {
    setValue('alcance', nuevo, { shouldValidate: false })
    setValue('colaborador_id', null)
    setValue('departamento_id', null)
  }

  const alcances: { valor: Alcance; titulo: string; descripcion: string }[] = [
    { valor: 'Individual', titulo: text.individual.titulo, descripcion: text.individual.descripcion },
    { valor: 'Departamento', titulo: text.departamento.titulo, descripcion: text.departamento.descripcion },
  ]

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5 rounded-xl border border-border bg-surface p-6">
      <div>
        <h2 className="text-xl font-bold">{text.titulo}</h2>
        <p className="mt-1 text-sm text-muted">{text.descripcion}</p>
      </div>

      {errores.general && <Alert>{errores.general}</Alert>}

      <fieldset className="flex flex-col gap-3">
        <legend className="mb-1 text-sm font-semibold">{text.alcanceLegend}</legend>
        <div role="radiogroup" aria-label={text.alcanceLegend} className="grid gap-3 sm:grid-cols-2">
          {alcances.map((opcion) => (
            <label
              key={opcion.valor}
              className={`flex cursor-pointer items-start gap-3 rounded-[10px] border p-4 ${
                alcance === opcion.valor ? 'border-accent bg-soft' : 'border-border bg-surface'
              }`}
            >
              <input
                type="radio"
                value={opcion.valor}
                checked={alcance === opcion.valor}
                onChange={() => cambiarAlcance(opcion.valor)}
                className="mt-1 accent-(--accent)"
              />
              <span>
                <span className="block text-[15px] font-semibold">{opcion.titulo}</span>
                <span className="block text-[13px] text-muted">{opcion.descripcion}</span>
              </span>
            </label>
          ))}
        </div>
        <input type="hidden" {...register('alcance')} />
      </fieldset>

      {alcance === 'Individual' ? (
        <div className="flex flex-col gap-3">
          <Controller
            control={control}
            name="colaborador_id"
            render={({ field }) => (
              <Combobox
                id="dnf-colaborador"
                label={text.colaboradorLabel}
                placeholder={text.colaboradorPlaceholder}
                options={opciones.colaboradores.map((c) => ({ value: String(c.id), label: c.nombre, description: c.puesto }))}
                value={field.value === null ? null : String(field.value)}
                onChange={(valor) => field.onChange(valor === null ? null : Number(valor))}
                error={errors.colaborador_id?.message ?? errores.colaborador_id}
              />
            )}
          />
          {colaborador && (
            <dl className="grid gap-3 rounded-lg border border-border bg-bg p-4 sm:grid-cols-2">
              <div>
                <dt className="text-[13px] font-semibold text-muted">{text.previewColaborador}</dt>
                <dd>{colaborador.nombre}</dd>
              </div>
              <div>
                <dt className="text-[13px] font-semibold text-muted">{text.puesto}</dt>
                <dd>{colaborador.puesto}</dd>
              </div>
            </dl>
          )}
        </div>
      ) : (
        <div className="flex flex-col gap-3">
          <SelectField
            id="dnf-departamento"
            label={text.departamentoLabel}
            value={departamentoId === null ? '' : String(departamentoId)}
            onChange={(evento) => setValue('departamento_id', evento.target.value === '' ? null : Number(evento.target.value), { shouldValidate: true })}
            error={errors.departamento_id?.message ?? errores.departamento_id}
            options={[
              { value: '', label: text.departamentoPlaceholder },
              ...opciones.departamentos.map((d) => ({ value: String(d.id), label: d.nombre })),
            ]}
          />
          {departamento && (
            <p className="text-sm text-muted">{text.colaboradoresEvaluables(departamento.colaboradores_evaluables)}</p>
          )}
        </div>
      )}

      <div>
        <Button type="submit" loading={enviando}>
          {enviando ? text.detectando : text.detectar}
        </Button>
      </div>
    </form>
  )
}
