import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconChartBar } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { Tabs } from '../../../../shared/ui/Tabs'
import { TIPOS_ACCION, type Agrupacion, type Meta, type OpcionesActividades, type TipoAccion } from '../api'
import { useCalificacionesPorActividad, useCalificacionesPorParticipante } from '../hooks/useActividades'
import { useCalificacionesParams } from '../hooks/useCalificacionesParams'
import { actividadesText } from '../text'
import { DetalleParticipante } from './DetalleParticipante'
import { TablaPorActividad, TablaPorParticipante } from './TablaCalificaciones'

const t = actividadesText.calificaciones
const f = actividadesText.admin.filtros
const POR_PAGINA = 10
const campoFecha = 'h-11 w-full rounded-lg border border-input-border bg-surface px-3 text-[15px]'

function PaginacionDe({ meta, onPageChange }: { meta: Meta; onPageChange: (page: number) => void }) {
  return (
    <Pagination
      page={meta.page}
      lastPage={meta.last_page}
      total={meta.total}
      from={(meta.page - 1) * meta.per_page + 1}
      to={Math.min(meta.page * meta.per_page, meta.total)}
      onPageChange={onPageChange}
    />
  )
}

const Cargando = () => (
  <div role="status" aria-label={actividadesText.loading} className="flex flex-col gap-2">
    {[0, 1, 2, 3].map((fila) => (
      <Skeleton key={fila} className="h-12" />
    ))}
  </div>
)

/** Calificaciones del Administrador: «Por actividad» y «Por participante», con filtros de fechas, tipo, departamento y plan. */
export function VistaCalificaciones({ opciones }: { opciones: OpcionesActividades | undefined }) {
  const params = useCalificacionesParams()
  const filtros = {
    fecha_desde: params.desde || undefined,
    fecha_hasta: params.hasta || undefined,
    tipo: params.tipo || undefined,
    departamento_id: params.departamento === '' ? undefined : params.departamento,
    plan_id: params.plan === '' ? undefined : params.plan,
  }
  const porActividad = useCalificacionesPorActividad({ ...filtros, page: params.page, per_page: POR_PAGINA }, params.grupo === 'actividad')
  const porParticipante = useCalificacionesPorParticipante({ ...filtros, page: params.page, per_page: POR_PAGINA }, params.grupo === 'participante')
  const consulta = params.grupo === 'actividad' ? porActividad : porParticipante
  const numero = (valor: string): number | '' => (valor === '' ? '' : Number(valor))

  return (
    <div className="flex flex-col gap-5">
      <div className="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-5 [&>*]:min-w-0">
        <div className="flex flex-col gap-1.5">
          <label htmlFor="calificaciones-desde" className="text-sm font-semibold">{f.desde}</label>
          <input id="calificaciones-desde" type="date" value={params.desde} max={params.hasta || undefined} onChange={(e) => params.update({ desde: e.target.value })} className={campoFecha} />
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor="calificaciones-hasta" className="text-sm font-semibold">{f.hasta}</label>
          <input id="calificaciones-hasta" type="date" value={params.hasta} min={params.desde || undefined} onChange={(e) => params.update({ hasta: e.target.value })} className={campoFecha} />
        </div>
        <SelectField
          id="calificaciones-tipo"
          label={f.tipo}
          value={params.tipo}
          onChange={(e) => params.update({ tipo: e.target.value as TipoAccion | '' })}
          options={[{ value: '', label: f.todos }, ...TIPOS_ACCION.map((tipo) => ({ value: tipo, label: actividadesText.tipo[tipo] }))]}
        />
        <SelectField
          id="calificaciones-departamento"
          label={f.departamento}
          value={String(params.departamento)}
          onChange={(e) => params.update({ departamento: numero(e.target.value) })}
          options={[{ value: '', label: f.todos }, ...(opciones?.departamentos ?? []).map((d) => ({ value: String(d.id), label: d.nombre }))]}
        />
        <SelectField
          id="calificaciones-plan"
          label={f.plan}
          value={String(params.plan)}
          onChange={(e) => params.update({ plan: numero(e.target.value) })}
          options={[{ value: '', label: f.todos }, ...(opciones?.planes ?? []).map((p) => ({ value: String(p.id), label: `${p.codigo} · ${p.nombre}` }))]}
        />
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-muted">{t.nota}</p>
        <Button variant="secondary" onClick={params.limpiar} disabled={!params.hayFiltros}>
          {f.limpiar}
        </Button>
      </div>

      <Tabs
        label={t.tabs}
        idBase="calificaciones"
        tabs={[
          { id: 'actividad', label: t.porActividad },
          { id: 'participante', label: t.porParticipante },
        ]}
        active={params.grupo}
        onChange={(id) => params.update({ grupo: id as Agrupacion })}
      >
        {consulta.isPending ? (
          <Cargando />
        ) : consulta.isError ? (
          <div className="rounded-xl border border-border bg-surface">
            <ErrorState title={actividadesText.error.title} description={actividadesText.error.description} retryLabel={actividadesText.error.retry} onRetry={() => void consulta.refetch()} />
          </div>
        ) : consulta.data.data.length === 0 ? (
          <div className="rounded-xl border border-border bg-surface">
            <EmptyState icon={<IconChartBar size={24} />} title={t.vacio} description="" />
          </div>
        ) : (
          <div className="flex flex-col gap-4">
            {params.grupo === 'actividad' && porActividad.data ? (
              <TablaPorActividad filas={porActividad.data.data} />
            ) : porParticipante.data ? (
              <TablaPorParticipante filas={porParticipante.data.data} seleccionado={params.participante === '' ? null : params.participante} onElegir={(id) => params.update({ participante: id })} />
            ) : null}
            <PaginacionDe meta={consulta.data.meta} onPageChange={(page) => params.update({ page })} />
          </div>
        )}
      </Tabs>

      {params.grupo === 'participante' && params.participante !== '' && (
        <DetalleParticipante colaboradorId={params.participante} filtros={filtros} onCerrar={() => params.update({ participante: '' })} />
      )}
    </div>
  )
}
