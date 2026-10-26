import { Button } from '../../../../shared/ui/Button'
import { SelectField, type SelectOption } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import { FILTRO_TODOS, GRUPOS, TIPOS_ACTUACION, type EstadoActuacion } from '../api'
import { movimientosText } from '../text'
import { SelectorDeColaborador } from './SelectorDeColaborador'

const text = movimientosText.list

const ESTADOS: EstadoActuacion[] = [
  'Presentada', 'EnRevision', 'Aprobada', 'Rechazada', 'Retirada', 'Programada', 'Aplicada', 'IncidenciaDeAplicacion', 'Informada', 'Revisada', 'Justificada',
]

export interface ValoresDeFiltros {
  tipo: string
  estado: string
  grupo: string
  colaboradorId: number | null
  desde: string
  hasta: string
}

interface FiltrosDeActuacionesProps {
  valores: ValoresDeFiltros
  onChange: (cambios: Partial<ValoresDeFiltros>) => void
  onClear: () => void
}

const todas = (etiqueta: string): SelectOption => ({ value: FILTRO_TODOS, label: etiqueta })

/** Filtros por tipo, estado, situación, colaborador y período; todos se guardan en la URL. */
export function FiltrosDeActuaciones({ valores, onChange, onClear }: FiltrosDeActuacionesProps) {
  return (
    <form role="search" aria-label={text.filtersLabel} className="grid gap-3 rounded-xl border border-border bg-surface p-4 md:grid-cols-3" onSubmit={(e) => e.preventDefault()}>
      <SelectField id="filtro-tipo" label={text.type} value={valores.tipo} onChange={(e) => onChange({ tipo: e.target.value })}
        options={[todas(text.all), ...TIPOS_ACTUACION.map((t) => ({ value: t, label: movimientosText.tipos[t] }))]} />
      <SelectField id="filtro-estado" label={text.status} value={valores.estado} onChange={(e) => onChange({ estado: e.target.value })}
        options={[todas(text.all), ...ESTADOS.map((e) => ({ value: e, label: movimientosText.estados[e] }))]} />
      <SelectField id="filtro-grupo" label={text.group} value={valores.grupo} onChange={(e) => onChange({ grupo: e.target.value })}
        options={[todas(text.all), ...GRUPOS.map((g) => ({ value: g, label: movimientosText.grupos[g] }))]} />
      <SelectorDeColaborador id="filtro-colaborador" label={text.person} placeholder="Nombre o código" valor={valores.colaboradorId} onChange={(id) => onChange({ colaboradorId: id })} />
      <TextField id="filtro-desde" type="date" label={text.from} value={valores.desde} onChange={(e) => onChange({ desde: e.target.value })} />
      <div className="flex flex-col gap-3">
        <TextField id="filtro-hasta" type="date" label={text.to} value={valores.hasta} onChange={(e) => onChange({ hasta: e.target.value })} />
        <Button variant="secondary" onClick={onClear}>{text.clear}</Button>
      </div>
    </form>
  )
}
