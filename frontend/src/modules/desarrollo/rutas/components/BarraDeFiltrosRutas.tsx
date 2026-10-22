import { Button } from '../../../../shared/ui/Button'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { ESTADOS_RUTA, TIPOS_RUTA, type EstadoRuta, type OpcionesRutas, type TipoRuta } from '../api'
import type { useFiltrosRutas } from '../hooks/useFiltrosRutas'
import { rutasText } from '../text'

const t = rutasText.lista.filtros

interface BarraDeFiltrosRutasProps {
  filtros: ReturnType<typeof useFiltrosRutas>
  opciones: OpcionesRutas | undefined
}

/** Búsqueda por nombre y filtros por tipo, departamento y estado; todo queda en la URL. */
export function BarraDeFiltrosRutas({ filtros, opciones }: BarraDeFiltrosRutasProps) {
  return (
    <div className="flex flex-wrap items-end gap-3">
      <div className="min-w-55 flex-1 basis-55 self-end">
        <SearchField id="rutas-buscar" label={t.buscar} placeholder={t.buscarPlaceholder} value={filtros.q} onChange={(e) => filtros.actualizar({ q: e.target.value })} />
      </div>
      <div className="w-55 max-w-full">
        <SelectField
          id="rutas-tipo"
          label={t.tipo}
          value={filtros.tipo}
          options={[{ value: '', label: t.todos }, ...TIPOS_RUTA.map((tipo) => ({ value: tipo, label: rutasText.tipos[tipo] }))]}
          onChange={(e) => filtros.actualizar({ tipo: e.target.value as TipoRuta | '' })}
        />
      </div>
      <div className="w-55 max-w-full">
        <SelectField
          id="rutas-departamento"
          label={t.departamento}
          value={filtros.departamento === '' ? '' : String(filtros.departamento)}
          options={[{ value: '', label: t.todas }, ...(opciones?.departamentos ?? []).map((d) => ({ value: String(d.id), label: d.nombre }))]}
          onChange={(e) => filtros.actualizar({ departamento: e.target.value === '' ? '' : Number(e.target.value) })}
        />
      </div>
      <div className="w-42.5 max-w-full">
        <SelectField
          id="rutas-estado"
          label={t.estado}
          value={filtros.estado}
          options={[{ value: '', label: t.todos }, ...ESTADOS_RUTA.map((estado) => ({ value: estado, label: rutasText.estados[estado] }))]}
          onChange={(e) => filtros.actualizar({ estado: e.target.value as EstadoRuta | '' })}
        />
      </div>
      {filtros.hayFiltros && (
        <Button variant="secondary" className="border-transparent text-accent" onClick={filtros.limpiar}>
          {t.limpiar}
        </Button>
      )}
    </div>
  )
}
