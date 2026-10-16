import { SelectField } from '../../../../shared/ui/SelectField'
import { ESTADOS, PRIORIDADES, type EstadoBrecha, type Prioridad } from '../api'
import type { FiltrosDiagnostico as Filtros } from '../orden'
import { dnfText } from '../text'

const text = dnfText.admin.filtros

interface FiltrosDiagnosticoProps {
  filtros: Filtros
  onChange: (filtros: Filtros) => void
}

/** Filtros de la tabla del diagnóstico: prioridad, estado y «solo con brecha». Es un componente controlado. */
export function FiltrosDiagnostico({ filtros, onChange }: FiltrosDiagnosticoProps) {
  return (
    <div className="flex flex-wrap items-end gap-4">
      <SelectField
        id="dnf-filtro-prioridad"
        label={text.prioridad}
        hideLabel
        value={filtros.prioridad}
        onChange={(e) => onChange({ ...filtros, prioridad: e.target.value as Prioridad | '' })}
        options={[
          { value: '', label: text.prioridadTodas },
          ...PRIORIDADES.map((p) => ({ value: p, label: dnfText.prioridad[p] })),
        ]}
      />
      <SelectField
        id="dnf-filtro-estado"
        label={text.estado}
        hideLabel
        value={filtros.estado}
        onChange={(e) => onChange({ ...filtros, estado: e.target.value as EstadoBrecha | '' })}
        options={[{ value: '', label: text.estadoTodos }, ...ESTADOS.map((s) => ({ value: s, label: dnfText.estado[s] }))]}
      />
      <label className="flex min-h-11 items-center gap-2 text-sm font-semibold">
        <input
          type="checkbox"
          checked={filtros.soloConBrecha}
          onChange={(e) => onChange({ ...filtros, soloConBrecha: e.target.checked })}
          className="size-4 accent-(--accent)"
        />
        {text.soloConBrecha}
      </label>
    </div>
  )
}
