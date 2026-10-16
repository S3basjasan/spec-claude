import { SelectField } from '../../../../shared/ui/SelectField'
import type { AreaFormativa } from '../api'
import { catalogoText } from '../text'

interface AreaFormativaSelectProps {
  id: string
  areas: AreaFormativa[]
  /** Id del área elegida, o `null` si la competencia no tiene área. */
  value: number | null
  onChange: (id: number | null) => void
  error?: string
  disabled?: boolean
}

/** Selector del área formativa de una competencia (una sola, o ninguna). */
export function AreaFormativaSelect({ id, areas, value, onChange, error, disabled }: AreaFormativaSelectProps) {
  return (
    <SelectField
      id={id}
      label={catalogoText.panel.areaLabel}
      value={value === null ? '' : String(value)}
      onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
      options={[{ value: '', label: catalogoText.panel.noArea }, ...areas.map((area) => ({ value: String(area.id), label: area.nombre }))]}
      error={error}
      disabled={disabled}
    />
  )
}
