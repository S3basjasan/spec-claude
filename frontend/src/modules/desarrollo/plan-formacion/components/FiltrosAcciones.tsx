import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { ESTADOS_PLAN, TIPOS_ACCION, type EstadoPlan, type OpcionesPlan, type TipoAccion } from '../api'
import type { CambiosAcciones } from '../hooks/useAccionesParams'
import { planText } from '../text'

const t = planText.acciones.filtros

interface FiltrosAccionesProps {
  estado: EstadoPlan | ''
  tipo: TipoAccion | ''
  competencia: number | ''
  desde: string
  hasta: string
  opciones: OpcionesPlan | undefined
  onChange: (cambios: CambiosAcciones) => void
  onLimpiar: () => void
}

/** Filtros comunes a las pestañas Calendario y Lista: estado del plan, tipo, competencia y rango de fechas. */
export function FiltrosAcciones({ estado, tipo, competencia, desde, hasta, opciones, onChange, onLimpiar }: FiltrosAccionesProps) {
  const hayFiltros = Boolean(estado || tipo || competencia || desde || hasta)
  const errorRango = desde !== '' && hasta !== '' && desde > hasta ? t.errorRango : undefined

  return (
    <div className="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <SelectField
        id="acciones-estado"
        label={t.estado}
        value={estado}
        onChange={(e) => onChange({ estado: e.target.value as EstadoPlan | '' })}
        options={[{ value: '', label: t.estadoTodos }, ...ESTADOS_PLAN.map((e) => ({ value: e, label: planText.estadoPlan[e] }))]}
      />
      <SelectField
        id="acciones-tipo"
        label={t.tipo}
        value={tipo}
        onChange={(e) => onChange({ tipo: e.target.value as TipoAccion | '' })}
        options={[{ value: '', label: t.tipoTodos }, ...TIPOS_ACCION.map((x) => ({ value: x, label: planText.tipo[x] }))]}
      />
      <SelectField
        id="acciones-competencia"
        label={t.competencia}
        value={competencia === '' ? '' : String(competencia)}
        onChange={(e) => onChange({ competencia: e.target.value === '' ? '' : Number(e.target.value) })}
        options={[{ value: '', label: t.competenciaTodas }, ...(opciones?.competencias ?? []).map((c) => ({ value: String(c.id), label: c.nombre }))]}
      />
      <div className="flex flex-col gap-1.5">
        <label htmlFor="acciones-desde" className="text-sm font-semibold">
          {t.desde}
        </label>
        <input id="acciones-desde" type="date" value={desde} onChange={(e) => onChange({ desde: e.target.value })} className="h-11 rounded-lg border border-input-border bg-surface px-3 text-[15px]" />
      </div>
      <div className="flex flex-col gap-1.5">
        <label htmlFor="acciones-hasta" className="text-sm font-semibold">
          {t.hasta}
        </label>
        <input
          id="acciones-hasta"
          type="date"
          value={hasta}
          onChange={(e) => onChange({ hasta: e.target.value })}
          aria-invalid={errorRango ? true : undefined}
          aria-describedby={errorRango ? 'acciones-hasta-error' : undefined}
          className={`h-11 rounded-lg border bg-surface px-3 text-[15px] ${errorRango ? 'border-danger' : 'border-input-border'}`}
        />
        {errorRango && (
          <p id="acciones-hasta-error" className="text-[13px] text-danger">
            {errorRango}
          </p>
        )}
      </div>
      <div>
        <Button variant="secondary" onClick={onLimpiar} disabled={!hayFiltros}>
          {t.limpiar}
        </Button>
      </div>
    </div>
  )
}
