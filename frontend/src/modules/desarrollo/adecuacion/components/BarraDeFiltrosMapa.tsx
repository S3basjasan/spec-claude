import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { PREPARACIONES, type FiltrosMapa, type OpcionesDeColaboradores } from '../api'
import { adecuacionText } from '../text'

const t = adecuacionText.mapa

interface Props {
  filtros: FiltrosMapa
  opciones: OpcionesDeColaboradores | undefined
  hayFiltros: boolean
  onCambiar: (cambios: Partial<Record<'departamento' | 'ruta' | 'preparacion', string>>) => void
  onLimpiar: () => void
}

/** Filtros del mapa: Departamento, Ruta y Preparación. Todos viven en la URL. */
export function BarraDeFiltrosMapa({ filtros, opciones, hayFiltros, onCambiar, onLimpiar }: Props) {
  return (
    <form role="search" aria-label={t.filtros} className="flex flex-wrap items-end gap-3" onSubmit={(e) => e.preventDefault()}>
      <div className="min-w-[200px] flex-1">
        <SelectField
          id="mapa-departamento"
          label={t.departamento}
          value={filtros.departamento_id === undefined ? '' : String(filtros.departamento_id)}
          options={[{ value: '', label: t.todos }, ...(opciones?.departamentos ?? []).map((d) => ({ value: String(d.id), label: d.nombre }))]}
          onChange={(e) => onCambiar({ departamento: e.target.value })}
        />
      </div>
      <div className="min-w-[200px] flex-1">
        <SelectField
          id="mapa-ruta"
          label={t.ruta}
          value={filtros.ruta_id === undefined ? '' : String(filtros.ruta_id)}
          options={[{ value: '', label: t.todas }, ...(opciones?.rutas ?? []).map((r) => ({ value: String(r.id), label: r.nombre }))]}
          onChange={(e) => onCambiar({ ruta: e.target.value })}
        />
      </div>
      <div className="w-48">
        <SelectField
          id="mapa-preparacion"
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
