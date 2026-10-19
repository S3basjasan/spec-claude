import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import { TIPOS_FORMACION, type OpcionesReportes, type TipoFormacion } from '../api'
import type { FiltrosReportesUrl } from '../hooks/useFiltrosReportes'
import { reportesText } from '../text'

const t = reportesText.filtros

interface BarraDeFiltrosProps {
  filtros: FiltrosReportesUrl
  opciones: OpcionesReportes | undefined
  /** Oculta el filtro de tipo (no aplica a la evaluación de impacto). */
  sinTipo?: boolean
}

/** Barra de filtros común a los reportes: rango de fechas, departamento, tipo de formación y plan, guardados en la URL. */
export function BarraDeFiltros({ filtros, opciones, sinTipo = false }: BarraDeFiltrosProps) {
  return (
    <form
      role="search"
      aria-label={t.label}
      onSubmit={(evento) => evento.preventDefault()}
      className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface p-4"
    >
      <div className="w-40">
        <TextField id="rep-desde" label={t.desde} type="date" value={filtros.desde} max={filtros.hasta || undefined} onChange={(e) => filtros.actualizar({ desde: e.target.value })} />
      </div>
      <div className="w-40">
        <TextField
          id="rep-hasta"
          label={t.hasta}
          type="date"
          value={filtros.hasta}
          min={filtros.desde || undefined}
          error={filtros.rangoValido ? undefined : t.rangoInvalido}
          onChange={(e) => filtros.actualizar({ hasta: e.target.value })}
        />
      </div>
      <div className="w-52">
        <SelectField
          id="rep-departamento"
          label={t.departamento}
          value={String(filtros.departamento)}
          options={[{ value: '', label: t.todos }, ...(opciones?.departamentos ?? []).map((d) => ({ value: String(d.id), label: d.nombre }))]}
          onChange={(e) => filtros.actualizar({ departamento: e.target.value === '' ? '' : Number(e.target.value) })}
        />
      </div>
      {!sinTipo && (
        <div className="w-48">
          <SelectField
            id="rep-tipo"
            label={t.tipo}
            value={filtros.tipo}
            options={[{ value: '', label: t.todos }, ...TIPOS_FORMACION.map((tipo) => ({ value: tipo, label: reportesText.nombreDeTipo(tipo) }))]}
            onChange={(e) => filtros.actualizar({ tipo: e.target.value as TipoFormacion | '' })}
          />
        </div>
      )}
      <div className="w-56">
        <SelectField
          id="rep-plan"
          label={t.plan}
          value={String(filtros.plan)}
          options={[{ value: '', label: t.todas }, ...(opciones?.planes ?? []).map((p) => ({ value: String(p.id), label: `${p.codigo} · ${p.nombre}` }))]}
          onChange={(e) => filtros.actualizar({ plan: e.target.value === '' ? '' : Number(e.target.value) })}
        />
      </div>
      {filtros.hayFiltros && (
        <Button type="button" variant="secondary" onClick={filtros.limpiar}>
          {t.limpiar}
        </Button>
      )}
    </form>
  )
}
