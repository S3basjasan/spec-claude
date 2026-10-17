import { Button } from '../../../../shared/ui/Button'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TIPOS_MICRO, type CompetenciaMicro, type FiltroEstadoMicro, type TipoMicro } from '../api'
import { microText } from '../text'

interface FiltrosRepositorioProps {
  busqueda: string
  tipo: TipoMicro | ''
  estado: FiltroEstadoMicro
  competencia: number | ''
  competencias: CompetenciaMicro[]
  hayFiltros: boolean
  onBusqueda: (texto: string) => void
  onTipo: (tipo: TipoMicro | '') => void
  onEstado: (estado: FiltroEstadoMicro) => void
  onCompetencia: (id: number | '') => void
  onLimpiar: () => void
}

const ESTADOS: FiltroEstadoMicro[] = ['Activos', 'Borrador', 'Publicado', 'Archivado', 'Todos']

/** Búsqueda por título y filtros por tipo, estado y competencia del repositorio. */
export function FiltrosRepositorio(p: FiltrosRepositorioProps) {
  const text = microText.admin

  return (
    <div className="flex flex-wrap items-end gap-3">
      <SearchField
        id="micro-busqueda"
        label={text.searchLabel}
        placeholder={text.searchPlaceholder}
        value={p.busqueda}
        onChange={(evento) => p.onBusqueda(evento.target.value)}
        maxLength={50}
        className="min-w-60 flex-1"
      />
      <SelectField
        id="micro-filtro-tipo"
        label={text.typeFilterLabel}
        hideLabel
        value={p.tipo}
        onChange={(evento) => p.onTipo(evento.target.value as TipoMicro | '')}
        options={[{ value: '', label: text.typeFilterAll }, ...TIPOS_MICRO.map((t) => ({ value: t, label: microText.tipo[t] }))]}
      />
      <SelectField
        id="micro-filtro-estado"
        label={text.statusFilterLabel}
        hideLabel
        value={p.estado}
        onChange={(evento) => p.onEstado(evento.target.value as FiltroEstadoMicro)}
        options={ESTADOS.map((e) => ({ value: e, label: text.statusFilters[e] }))}
      />
      <SelectField
        id="micro-filtro-competencia"
        label={text.competenceFilterLabel}
        hideLabel
        value={p.competencia === '' ? '' : String(p.competencia)}
        onChange={(evento) => p.onCompetencia(evento.target.value === '' ? '' : Number(evento.target.value))}
        options={[{ value: '', label: text.competenceFilterAll }, ...p.competencias.map((c) => ({ value: String(c.id), label: c.nombre }))]}
      />
      {p.hayFiltros && (
        <Button variant="secondary" onClick={p.onLimpiar}>
          {text.clearFilters}
        </Button>
      )}
    </div>
  )
}
