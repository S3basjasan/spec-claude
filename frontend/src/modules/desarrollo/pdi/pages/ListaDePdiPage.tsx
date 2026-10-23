import { Link, useSearchParams } from 'react-router-dom'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { Tabs, type TabItem } from '../../../../shared/ui/Tabs'
import type { PestanaLista } from '../api'
import { BarraDeFiltrosPdi } from '../components/BarraDeFiltrosPdi'
import { BloqueSinPlan } from '../components/BloqueSinPlan'
import { KpisDePdi } from '../components/KpisDePdi'
import { TablaDePdi } from '../components/TablaDePdi'
import { useFiltrosPdi } from '../hooks/useFiltrosPdi'
import { useListaPdi, useOpciones, usePermisosPdi } from '../hooks/usePdi'
import { PERMISOS, tienePermiso } from '../permisos'
import { pdiText } from '../text'
import { SeguimientoGanttPage } from './SeguimientoGanttPage'

const t = pdiText.lista

/** Página del administrador: «Plan de Desarrollo Individual (PDI)» con las pestañas Lista de PDI y Seguimiento (Gantt). */
export function ListaDePdiPage() {
  const { data: permisos } = usePermisosPdi()
  const [params, setParams] = useSearchParams()
  const { lista, cambiar, limpiar, hayFiltros, paginaDeSinPlan } = useFiltrosPdi()
  const opciones = useOpciones('', tienePermiso(permisos, PERMISOS.gestionar))
  const puedeVerLista = tienePermiso(permisos, PERMISOS.ver)
  const puedeVerGantt = tienePermiso(permisos, PERMISOS.gantt)
  const consulta = useListaPdi(lista, puedeVerLista)

  const visibles: PestanaLista[] = [...(puedeVerLista ? (['lista'] as const) : []), ...(puedeVerGantt ? (['gantt'] as const) : [])]
  const pedida = params.get('pestana')
  const activa: PestanaLista = visibles.find((p) => p === pedida) ?? visibles[0] ?? 'lista'
  const items: TabItem[] = visibles.map((p) => ({ id: p, label: pdiText.pestanasLista[p] }))
  const departamentos = opciones.data?.departamentos ?? []
  const responsables = opciones.data?.responsables ?? []

  const cambiarPestana = (id: string) => {
    const siguiente = new URLSearchParams()
    if (id !== visibles[0]) siguiente.set('pestana', id)
    setParams(siguiente, { replace: true })
  }

  return (
    <div className="mx-auto flex max-w-[1200px] flex-col gap-5">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          {/* <p className="m-0 text-[13px] font-semibold text-muted">{pdiText.sobretitulo}</p> */}
          <h1 className="m-0 mt-0.5 text-2xl font-bold">{pdiText.titulo}</h1>
          <p className="m-0 mt-1 text-muted">{pdiText.descripcion}</p>
        </div>
        {tienePermiso(permisos, PERMISOS.gestionar) && (
          <Link to="/pdi/nuevo" className="inline-flex min-h-11 items-center rounded-lg bg-accent px-5 font-semibold text-onaccent hover:bg-accent-hover">
            {pdiText.nuevo}
          </Link>
        )}
      </header>

      <Tabs label={pdiText.titulo} tabs={items} active={activa} onChange={cambiarPestana} idBase="pdi-lista" scrollable>
        {activa === 'lista' && (
          <div className="flex flex-col gap-5">
            {consulta.isSuccess && <KpisDePdi kpis={consulta.data.meta.kpis} />}

            <BloqueSinPlan departamentoId={lista.departamento_id} pagina={paginaDeSinPlan} habilitado={tienePermiso(permisos, PERMISOS.gestionar)} onPagina={(p) => cambiar({ sinplan: String(p) })} />

            <BarraDeFiltrosPdi
              valores={{
                tipo: lista.tipo ?? '',
                estado: lista.estado ?? '',
                departamento: String(lista.departamento_id ?? ''),
                responsable: String(lista.responsable_id ?? ''),
                desde: lista.fecha_desde ?? '',
                hasta: lista.fecha_hasta ?? '',
              }}
              departamentos={departamentos}
              responsables={responsables}
              conEstadoYFechas
              hayFiltros={hayFiltros}
              onChange={(clave, valor) => cambiar({ [clave]: valor })}
              onLimpiar={limpiar}
            />

            {consulta.isPending && (
              <div role="status" aria-label={pdiText.loading} className="flex flex-col gap-2">
                <Skeleton className="h-12" />
                <Skeleton className="h-12" />
                <Skeleton className="h-12" />
              </div>
            )}
            {consulta.isError && (
              <div className="rounded-xl border border-border bg-surface">
                <ErrorState title={t.error.titulo} description={t.error.descripcion} retryLabel={t.error.reintentar} onRetry={() => void consulta.refetch()} />
              </div>
            )}
            {consulta.isSuccess && consulta.data.data.length === 0 && (
              <div className="rounded-xl border border-border bg-surface">
                <EmptyState icon={<IconFileText />} title={t.vacio.titulo} description={t.vacio.descripcion} />
              </div>
            )}
            {consulta.isSuccess && consulta.data.data.length > 0 && (
              <>
                <p role="status" className="m-0 text-[13px] text-muted">
                  {t.resumen(consulta.data.meta.total)}
                </p>
                <TablaDePdi planes={consulta.data.data} />
                <Pagination
                  page={consulta.data.meta.page}
                  lastPage={consulta.data.meta.last_page}
                  total={consulta.data.meta.total}
                  from={(consulta.data.meta.page - 1) * consulta.data.meta.per_page + 1}
                  to={(consulta.data.meta.page - 1) * consulta.data.meta.per_page + consulta.data.data.length}
                  onPageChange={(p) => cambiar({ page: String(p) })}
                />
              </>
            )}
          </div>
        )}

        {activa === 'gantt' && <SeguimientoGanttPage departamentos={departamentos} responsables={responsables} />}
      </Tabs>
    </div>
  )
}
