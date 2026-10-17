import { Button } from '../../../../shared/ui/Button'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { ESTADOS_ACTIVIDAD, TIPOS_ACCION, type EstadoActividad, type OpcionesActividades, type TipoAccion } from '../api'
import { useActividadesParams } from '../hooks/useActividadesParams'
import { actividadesText } from '../text'

const t = actividadesText.admin.filtros

interface FiltrosActividadesProps {
  params: ReturnType<typeof useActividadesParams>
  opciones: OpcionesActividades | undefined
  /** Texto del contador «Mostrando N de M»; vacío mientras carga. */
  resumen?: string
}

const campoFecha = 'h-11 w-full rounded-lg border border-input-border bg-surface px-3 text-[15px]'

/** Búsqueda por nombre y filtros por tipo, estado, departamento, competencia, plan y rango de fechas; todo en la URL. */
export function FiltrosActividades({ params, opciones, resumen }: FiltrosActividadesProps) {
  const numero = (valor: string): number | '' => (valor === '' ? '' : Number(valor))

  return (
    <div className="flex flex-col gap-3">
      <div className="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4 [&>*]:min-w-0">
        <SearchField id="actividades-buscar" label={t.buscar} placeholder={t.buscarPlaceholder} value={params.q} onChange={(e) => params.update({ q: e.target.value })} />
        <SelectField
          id="actividades-tipo"
          label={t.tipo}
          value={params.tipo}
          onChange={(e) => params.update({ tipo: e.target.value as TipoAccion | '' })}
          options={[{ value: '', label: t.todos }, ...TIPOS_ACCION.map((tipo) => ({ value: tipo, label: actividadesText.tipo[tipo] }))]}
        />
        <SelectField
          id="actividades-estado"
          label={t.estado}
          value={params.estado}
          onChange={(e) => params.update({ estado: e.target.value as EstadoActividad | '' })}
          options={[{ value: '', label: t.todos }, ...ESTADOS_ACTIVIDAD.map((estado) => ({ value: estado, label: actividadesText.estado[estado] }))]}
        />
        <SelectField
          id="actividades-departamento"
          label={t.departamento}
          value={String(params.departamento)}
          onChange={(e) => params.update({ departamento: numero(e.target.value) })}
          options={[{ value: '', label: t.todos }, ...(opciones?.departamentos ?? []).map((d) => ({ value: String(d.id), label: d.nombre }))]}
        />
        <SelectField
          id="actividades-competencia"
          label={t.competencia}
          value={String(params.competencia)}
          onChange={(e) => params.update({ competencia: numero(e.target.value) })}
          options={[{ value: '', label: t.todas }, ...(opciones?.competencias ?? []).map((c) => ({ value: String(c.id), label: c.nombre }))]}
        />
        <SelectField
          id="actividades-plan"
          label={t.plan}
          value={String(params.plan)}
          onChange={(e) => params.update({ plan: numero(e.target.value) })}
          options={[{ value: '', label: t.todos }, ...(opciones?.planes ?? []).map((p) => ({ value: String(p.id), label: `${p.codigo} · ${p.nombre}` }))]}
        />
        <div className="flex flex-col gap-1.5">
          <label htmlFor="actividades-desde" className="text-sm font-semibold">
            {t.desde}
          </label>
          <input id="actividades-desde" type="date" value={params.desde} max={params.hasta || undefined} onChange={(e) => params.update({ desde: e.target.value })} className={campoFecha} />
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor="actividades-hasta" className="text-sm font-semibold">
            {t.hasta}
          </label>
          <input id="actividades-hasta" type="date" value={params.hasta} min={params.desde || undefined} onChange={(e) => params.update({ hasta: e.target.value })} className={campoFecha} />
        </div>
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p role="status" aria-live="polite" className="text-sm text-muted">
          {resumen}
        </p>
        <Button variant="secondary" onClick={params.limpiar} disabled={!params.hayFiltros}>
          {t.limpiar}
        </Button>
      </div>
    </div>
  )
}
