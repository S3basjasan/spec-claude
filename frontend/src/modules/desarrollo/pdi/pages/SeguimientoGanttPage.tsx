import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconList } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { Referencia } from '../api'
import { BarraDeFiltrosPdi } from '../components/BarraDeFiltrosPdi'
import { LineaDeTiempoGantt } from '../components/LineaDeTiempoGantt'
import { useFiltrosPdi } from '../hooks/useFiltrosPdi'
import { useGantt } from '../hooks/usePdi'
import { pdiText } from '../text'

const t = pdiText.gantt

interface SeguimientoGanttPageProps {
  departamentos: Referencia[]
  responsables: Referencia[]
}

/** Pestaña «Seguimiento (Gantt)»: acciones e hitos de los planes Acordados con filtros por tipo, responsable y departamento. */
export function SeguimientoGanttPage({ departamentos, responsables }: SeguimientoGanttPageProps) {
  const { gantt, cambiar, limpiar, hayFiltros } = useFiltrosPdi()
  const consulta = useGantt(gantt)

  return (
    <div className="flex flex-col gap-4">
      <h2 className="m-0 text-lg font-bold">{t.titulo}</h2>
      <BarraDeFiltrosPdi
        valores={{ tipo: gantt.tipo ?? '', estado: '', departamento: String(gantt.departamento_id ?? ''), responsable: String(gantt.responsable_id ?? ''), desde: '', hasta: '' }}
        departamentos={departamentos}
        responsables={responsables}
        conEstadoYFechas={false}
        hayFiltros={hayFiltros}
        onChange={(clave, valor) => cambiar({ [clave]: valor })}
        onLimpiar={limpiar}
      />

      {consulta.isPending && (
        <div role="status" aria-label={pdiText.loading} className="flex flex-col gap-2">
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
        </div>
      )}
      {consulta.isError && (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={t.error.titulo} description={t.error.descripcion} retryLabel={t.error.reintentar} onRetry={() => void consulta.refetch()} />
        </div>
      )}
      {consulta.isSuccess && consulta.data.data.length === 0 && (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconList />} title={t.vacio.titulo} description={t.vacio.descripcion} />
        </div>
      )}
      {consulta.isSuccess && consulta.data.data.length > 0 && (
        <>
          <LineaDeTiempoGantt filas={consulta.data.data} hoy={consulta.data.hoy} />
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
  )
}
