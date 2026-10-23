import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import { ESTADOS_PDI, TIPOS_PDI, type Referencia } from '../api'
import { pdiText } from '../text'

const t = pdiText.lista.filtros

interface BarraDeFiltrosPdiProps {
  valores: { tipo: string; estado: string; departamento: string; responsable: string; desde: string; hasta: string }
  departamentos: Referencia[]
  responsables: Referencia[]
  /** El Gantt solo filtra por tipo, responsable y departamento. */
  conEstadoYFechas: boolean
  hayFiltros: boolean
  onChange: (clave: 'tipo' | 'estado' | 'departamento' | 'responsable' | 'desde' | 'hasta', valor: string) => void
  onLimpiar: () => void
}

const todos = { value: '', label: t.todos }

/** Filtros de la lista de planes y del Gantt; se guardan en la URL. */
export function BarraDeFiltrosPdi({ valores, departamentos, responsables, conEstadoYFechas, hayFiltros, onChange, onLimpiar }: BarraDeFiltrosPdiProps) {
  return (
    <form role="search" aria-label={t.aria} className="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4" onSubmit={(e) => e.preventDefault()}>
      <SelectField id="filtro-tipo" label={t.tipo} options={[todos, ...TIPOS_PDI.map((x) => ({ value: x, label: pdiText.tipos[x] }))]} value={valores.tipo} onChange={(e) => onChange('tipo', e.target.value)} />
      {conEstadoYFechas && (
        <SelectField id="filtro-estado" label={t.estado} options={[todos, ...ESTADOS_PDI.map((x) => ({ value: x, label: pdiText.estados[x] }))]} value={valores.estado} onChange={(e) => onChange('estado', e.target.value)} />
      )}
      <SelectField id="filtro-departamento" label={t.departamento} options={[todos, ...departamentos.map((d) => ({ value: String(d.id), label: d.nombre }))]} value={valores.departamento} onChange={(e) => onChange('departamento', e.target.value)} />
      <SelectField id="filtro-responsable" label={t.responsable} options={[todos, ...responsables.map((r) => ({ value: String(r.id), label: r.nombre }))]} value={valores.responsable} onChange={(e) => onChange('responsable', e.target.value)} />
      {conEstadoYFechas && (
        <>
          <TextField id="filtro-desde" type="date" label={t.desde} value={valores.desde} onChange={(e) => onChange('desde', e.target.value)} />
          <TextField id="filtro-hasta" type="date" label={t.hasta} value={valores.hasta} onChange={(e) => onChange('hasta', e.target.value)} />
        </>
      )}
      {hayFiltros && (
        <div>
          <Button variant="secondary" onClick={onLimpiar}>
            {t.limpiar}
          </Button>
        </div>
      )}
    </form>
  )
}
