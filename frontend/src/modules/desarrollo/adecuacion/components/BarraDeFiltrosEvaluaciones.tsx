import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import { PREPARACIONES, type FiltrosEvaluaciones, type OpcionesDeColaboradores } from '../api'
import { adecuacionText } from '../text'

const t = adecuacionText.evaluaciones

interface Props {
  filtros: FiltrosEvaluaciones
  opciones: OpcionesDeColaboradores | undefined
  hayFiltros: boolean
  onCambiar: (cambios: Partial<Record<'desde' | 'hasta' | 'colaborador' | 'puesto' | 'preparacion', string>>) => void
  onLimpiar: () => void
}

/** Filtros del historial: fechas, colaborador, puesto objetivo y preparación. Todos viven en la URL. */
export function BarraDeFiltrosEvaluaciones({ filtros, opciones, hayFiltros, onCambiar, onLimpiar }: Props) {
  return (
    <form role="search" aria-label={t.filtros} className="flex flex-wrap items-end gap-3" onSubmit={(e) => e.preventDefault()}>
      <div className="w-40">
        <TextField id="eval-desde" type="date" label={t.desde} value={filtros.fecha_desde ?? ''} onChange={(e) => onCambiar({ desde: e.target.value })} />
      </div>
      <div className="w-40">
        <TextField id="eval-hasta" type="date" label={t.hasta} value={filtros.fecha_hasta ?? ''} onChange={(e) => onCambiar({ hasta: e.target.value })} />
      </div>
      <div className="min-w-[200px] flex-1">
        <SelectField
          id="eval-colaborador"
          label={t.colaborador}
          value={filtros.colaborador_id === undefined ? '' : String(filtros.colaborador_id)}
          options={[{ value: '', label: t.todos }, ...(opciones?.colaboradores ?? []).map((c) => ({ value: String(c.id), label: c.nombre }))]}
          onChange={(e) => onCambiar({ colaborador: e.target.value })}
        />
      </div>
      <div className="min-w-[200px] flex-1">
        <SelectField
          id="eval-puesto"
          label={t.puesto}
          value={filtros.puesto_objetivo_id === undefined ? '' : String(filtros.puesto_objetivo_id)}
          options={[{ value: '', label: t.todos }, ...(opciones?.puestos ?? []).map((p) => ({ value: String(p.id), label: p.nombre }))]}
          onChange={(e) => onCambiar({ puesto: e.target.value })}
        />
      </div>
      <div className="w-44">
        <SelectField
          id="eval-preparacion"
          label={t.preparacion}
          value={filtros.preparacion ?? ''}
          options={[{ value: '', label: t.todas }, ...PREPARACIONES.map((p) => ({ value: p, label: adecuacionText.preparacion[p] }))]}
          onChange={(e) => onCambiar({ preparacion: e.target.value })}
        />
      </div>
      {hayFiltros && (
        <Button variant="secondary" type="button" onClick={onLimpiar}>
          {t.limpiar}
        </Button>
      )}
    </form>
  )
}
