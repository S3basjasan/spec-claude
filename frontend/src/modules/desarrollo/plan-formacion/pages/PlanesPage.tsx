import { Link } from 'react-router-dom'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { ESTADOS_PLAN, type FiltrosPlanes } from '../api'
import { TablaPlanes } from '../components/TablaPlanes'
import { usePlanParams } from '../hooks/usePlanParams'
import { usePlanes } from '../hooks/usePlanes'
import { planText } from '../text'

const t = planText.planes
const POR_PAGINA = 10

/** Planes de formación (Administrador): tabla filtrable por año, estado y nombre, con los botones que permite cada estado. */
export function PlanesPage() {
  const params = usePlanParams()
  const filtros: FiltrosPlanes = {
    anio: params.anio === '' ? undefined : params.anio,
    estado: params.estado === '' ? undefined : params.estado,
    q: params.q || undefined,
    page: params.page,
    per_page: POR_PAGINA,
  }
  const planes = usePlanes(filtros)
  const hayFiltros = Boolean(params.anio || params.estado || params.q)
  const meta = planes.data?.meta

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{t.title}</h1>
          <p className="mt-1.5 text-base text-muted">{t.subtitle}</p>
        </div>
        <div className="flex flex-wrap gap-3">
          <Link to="/plan-formacion/acciones" className="inline-flex h-11 items-center rounded-lg border border-border bg-surface px-4 text-[15px] font-semibold hover:bg-soft">
            {t.verAcciones}
          </Link>
          <Link to="/plan-formacion/nuevo" className="inline-flex h-11 items-center rounded-lg bg-accent px-4 text-[15px] font-semibold text-onaccent hover:bg-accent-hover">
            {t.nuevo}
          </Link>
        </div>
      </div>

      <div className="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <SearchField id="planes-buscar" label={t.filtros.buscar} placeholder={t.filtros.buscarPlaceholder} value={params.q} onChange={(e) => params.update({ q: e.target.value })} />
        <div className="flex flex-col gap-1.5">
          <label htmlFor="planes-anio" className="text-sm font-semibold">
            {t.filtros.anio}
          </label>
          <input
            id="planes-anio"
            inputMode="numeric"
            maxLength={4}
            placeholder={t.filtros.anioTodos}
            value={params.anio === '' ? '' : String(params.anio)}
            onChange={(e) => {
              const valor = e.target.value.replace(/\D/g, '')
              params.update({ anio: valor === '' ? '' : Number(valor) })
            }}
            className="h-11 rounded-lg border border-input-border bg-surface px-3 text-[15px]"
          />
        </div>
        <SelectField
          id="planes-estado"
          label={t.filtros.estado}
          value={params.estado}
          onChange={(e) => params.update({ estado: e.target.value as (typeof ESTADOS_PLAN)[number] | '' })}
          options={[{ value: '', label: t.filtros.estadoTodos }, ...ESTADOS_PLAN.map((estado) => ({ value: estado, label: planText.estadoPlan[estado] }))]}
        />
        <div>
          <Button variant="secondary" onClick={params.limpiar} disabled={!hayFiltros}>
            {t.filtros.limpiar}
          </Button>
        </div>
      </div>

      {planes.isPending ? (
        <div role="status" aria-label={t.tableLabel} className="flex flex-col gap-2">
          {[0, 1, 2, 3].map((fila) => (
            <Skeleton key={fila} className="h-12" />
          ))}
        </div>
      ) : planes.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={planText.error.title} description={planText.error.description} retryLabel={planText.error.retry} onRetry={() => void planes.refetch()} />
        </div>
      ) : planes.data.data.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconFileText size={24} />} title={hayFiltros ? t.sinCoincidencias : t.vacio.title} description={hayFiltros ? '' : t.vacio.description} />
        </div>
      ) : (
        <>
          <TablaPlanes planes={planes.data.data} />
          {meta && (
            <Pagination
              page={meta.page}
              lastPage={meta.last_page}
              total={meta.total}
              from={(meta.page - 1) * meta.per_page + 1}
              to={Math.min(meta.page * meta.per_page, meta.total)}
              onPageChange={(page) => params.update({ page })}
            />
          )}
        </>
      )}
    </div>
  )
}
