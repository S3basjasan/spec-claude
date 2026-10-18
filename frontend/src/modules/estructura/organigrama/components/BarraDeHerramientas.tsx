import { Button } from '../../../../shared/ui/Button'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField, type SelectOption } from '../../../../shared/ui/SelectField'
import type { UnidadDelFiltro } from '../api'
import { organigramaText } from '../text'

interface BarraDeHerramientasProps {
  busqueda: string
  unidad: number | null
  unidades: UnidadDelFiltro[]
  onBuscar: (texto: string) => void
  onUnidad: (unidad: number | null) => void
  onExpandirTodo: () => void
  onContraerTodo: () => void
  onLimpiar: () => void
}

/** Sangría de texto de una opción según su nivel (los `<option>` no admiten estilos de sangría fiables). */
const sangria = (nivel: number) => '   '.repeat(nivel)

/** Búsqueda, filtro de unidad, expandir y contraer todo, y «Limpiar filtros» (solo con búsqueda o unidad). */
export function BarraDeHerramientas({
  busqueda,
  unidad,
  unidades,
  onBuscar,
  onUnidad,
  onExpandirTodo,
  onContraerTodo,
  onLimpiar,
}: BarraDeHerramientasProps) {
  const text = organigramaText.toolbar
  const opciones: SelectOption[] = [
    { value: '', label: text.allUnits },
    ...unidades.map((u) => ({ value: String(u.id), label: `${sangria(u.nivel_arbol)}${u.nombre}` })),
  ]

  return (
    <div className="flex flex-wrap items-end gap-3">
      <div className="flex min-w-65 max-w-85 flex-1 flex-col gap-1.5">
        <span className="text-[13px] font-semibold text-muted" aria-hidden="true">
          {text.searchLabel}
        </span>
        <SearchField
          id="busqueda-organigrama"
          label={text.searchLabel}
          placeholder={text.searchPlaceholder}
          value={busqueda}
          maxLength={50}
          onChange={(evento) => onBuscar(evento.target.value)}
        />
      </div>
      <SelectField
        id="unidad-organigrama"
        label={text.unitLabel}
        value={unidad === null ? '' : String(unidad)}
        onChange={(evento) => onUnidad(evento.target.value === '' ? null : Number(evento.target.value))}
        options={opciones}
        className="min-w-65"
      />
      <Button variant="secondary" onClick={onExpandirTodo}>
        {text.expandAll}
      </Button>
      <Button variant="secondary" onClick={onContraerTodo}>
        {text.collapseAll}
      </Button>
      {(busqueda.trim() !== '' || unidad !== null) && (
        <Button variant="secondary" onClick={onLimpiar}>
          {text.clearFilters}
        </Button>
      )}
    </div>
  )
}
