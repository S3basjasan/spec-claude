import type { UseFormRegisterReturn } from 'react-hook-form'
import { IconChevronDown, IconChevronUp, IconX } from '../../../../shared/ui/icons'
import { SelectField, type SelectOption } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'

interface MoverProps {
  subirLabel: string
  bajarLabel: string
  puedeSubir: boolean
  puedeBajar: boolean
  onSubir: () => void
  onBajar: () => void
}

interface FilaTipoTextoProps {
  /** Base de los identificadores de la fila (únicos en la página). */
  id: string
  /** Número visible de la fila (solo las funciones, que son una lista ordenada). */
  numero?: number
  tipoLabel: string
  textoLabel: string
  tipoOptions: SelectOption[]
  tipoProps: UseFormRegisterReturn
  textoProps: UseFormRegisterReturn
  /** Longitud actual del texto, para el contador `n/máximo`. */
  longitud: number
  maximo: number
  errorTipo?: string
  errorTexto?: string
  quitarLabel: string
  onQuitar: () => void
  /** Botones Subir y Bajar (solo en las funciones). */
  mover?: MoverProps
}

const botonIcono =
  'flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-border bg-surface text-text hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45'

/** Fila repetible de un `fieldset`: selector de tipo, texto con contador y botones (design/DESIGN.md §4). */
export function FilaTipoTexto({
  id,
  numero,
  tipoLabel,
  textoLabel,
  tipoOptions,
  tipoProps,
  textoProps,
  longitud,
  maximo,
  errorTipo,
  errorTexto,
  quitarLabel,
  onQuitar,
  mover,
}: FilaTipoTextoProps) {
  return (
    <div className="flex flex-wrap items-start gap-2">
      {numero !== undefined && (
        <span aria-hidden="true" className="flex h-11 w-6 shrink-0 items-center justify-end font-semibold text-muted">
          {numero}
        </span>
      )}
      <SelectField id={`${id}-tipo`} label={tipoLabel} hideLabel options={tipoOptions} error={errorTipo} className="w-47.5" {...tipoProps} />
      <div className="min-w-0 flex-[1_1_220px]">
        <TextField
          id={`${id}-texto`}
          label={textoLabel}
          hideLabel
          maxLength={maximo}
          hint={`${longitud}/${maximo}`}
          error={errorTexto}
          {...textoProps}
        />
      </div>
      {mover && (
        <>
          <button type="button" aria-label={mover.subirLabel} disabled={!mover.puedeSubir} onClick={mover.onSubir} className={botonIcono}>
            <IconChevronUp size={18} />
          </button>
          <button type="button" aria-label={mover.bajarLabel} disabled={!mover.puedeBajar} onClick={mover.onBajar} className={botonIcono}>
            <IconChevronDown size={18} />
          </button>
        </>
      )}
      <button type="button" aria-label={quitarLabel} onClick={onQuitar} className={`${botonIcono} text-danger`}>
        <IconX size={18} />
      </button>
    </div>
  )
}
