import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconUsers } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { Alert } from '../../../../shared/ui/Alert'
import { useOpcionesGenerales } from '../hooks/useAdecuacion'
import { useFiltrosMapa, useMapaDeTalento } from '../hooks/useMapaDeTalento'
import { adecuacionText } from '../text'
import { BarraDeFiltrosMapa } from './BarraDeFiltrosMapa'
import { KpisDelMapa } from './KpisDelMapa'
import { TablaDelMapa } from './TablaDelMapa'

const t = adecuacionText.mapa

/** Pestaña «Mapa de talento»: la adecuación vigente de cada colaborador por puesto objetivo, filtrable. */
export function PestanaMapa() {
  const { filtros, cambiar, limpiar, hayFiltros } = useFiltrosMapa()
  const opciones = useOpcionesGenerales()
  const consulta = useMapaDeTalento(filtros)
  const mapa = consulta.data

  return (
    <div className="flex flex-col gap-5">
      {mapa !== undefined && <KpisDelMapa kpis={mapa.kpis} />}

      <BarraDeFiltrosMapa filtros={filtros} opciones={opciones.data} hayFiltros={hayFiltros} onCambiar={cambiar} onLimpiar={limpiar} />

      {consulta.isPending && (
        <div role="status" aria-label={adecuacionText.loading} className="flex flex-col gap-2">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
        </div>
      )}

      {consulta.isError && (
        <ErrorState title={adecuacionText.errores.cargar.title} description={adecuacionText.errores.cargar.description} retryLabel={adecuacionText.reintentar} onRetry={() => void consulta.refetch()} />
      )}

      {mapa !== undefined && mapa.fuentes_no_disponibles.length > 0 && <Alert tone="neutral">{adecuacionText.errores.fuentes}</Alert>}

      {mapa !== undefined && mapa.data.length === 0 && (
        <EmptyState icon={<IconUsers />} title={hayFiltros ? t.vacio.title : t.sinDatos.title} description={hayFiltros ? t.vacio.description : t.sinDatos.description} />
      )}

      {mapa !== undefined && mapa.data.length > 0 && (
        <>
          <p role="status" aria-live="polite" className="m-0 text-sm text-muted">
            {t.resumen(mapa.meta.total)}
          </p>
          <TablaDelMapa filas={mapa.data} />
          <Pagination
            page={mapa.meta.page}
            lastPage={mapa.meta.last_page}
            total={mapa.meta.total}
            from={(mapa.meta.page - 1) * mapa.meta.per_page + 1}
            to={Math.min(mapa.meta.total, mapa.meta.page * mapa.meta.per_page)}
            onPageChange={(page) => cambiar({ page: String(page) })}
          />
          <p className="m-0 text-[13px] text-muted">{t.nota}</p>
        </>
      )}
    </div>
  )
}
