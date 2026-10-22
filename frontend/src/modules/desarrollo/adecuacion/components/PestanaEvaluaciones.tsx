import { Link } from 'react-router-dom'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconList } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useOpcionesGenerales } from '../hooks/useAdecuacion'
import { useEvaluaciones, useFiltrosEvaluaciones } from '../hooks/useEvaluaciones'
import { adecuacionText } from '../text'
import { BarraDeFiltrosEvaluaciones } from './BarraDeFiltrosEvaluaciones'
import { PreparacionBadge } from './Etiquetas'

const t = adecuacionText.evaluaciones

/** Pestaña «Evaluaciones»: historial de adecuaciones con filtros; cada fila abre el detalle de solo lectura. */
export function PestanaEvaluaciones() {
  const { filtros, cambiar, limpiar, hayFiltros } = useFiltrosEvaluaciones()
  const opciones = useOpcionesGenerales()
  const consulta = useEvaluaciones(filtros)
  const pagina = consulta.data

  return (
    <div className="flex flex-col gap-5">
      <BarraDeFiltrosEvaluaciones filtros={filtros} opciones={opciones.data} hayFiltros={hayFiltros} onCambiar={cambiar} onLimpiar={limpiar} />

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

      {pagina !== undefined && pagina.data.length === 0 && (
        <EmptyState icon={<IconList />} title={hayFiltros ? t.sinResultados.title : t.vacio.title} description={hayFiltros ? t.sinResultados.description : t.vacio.description} />
      )}

      {pagina !== undefined && pagina.data.length > 0 && (
        <>
          <p role="status" aria-live="polite" className="m-0 text-sm text-muted">
            {t.resumen(pagina.meta.total)}
          </p>
          <div className="overflow-x-auto rounded-xl border border-border bg-surface">
            <table className="w-full min-w-[720px] border-collapse text-sm">
              <caption className="sr-only">{t.titulo}</caption>
              <thead>
                <tr className="text-left text-[13px] text-muted">
                  <th scope="col" className="py-2.5 pl-5 pr-3 font-semibold">{t.codigo}</th>
                  <th scope="col" className="px-3 py-2.5 font-semibold">{t.fecha}</th>
                  <th scope="col" className="px-3 py-2.5 font-semibold">{t.colaborador}</th>
                  <th scope="col" className="px-3 py-2.5 font-semibold">{t.puesto}</th>
                  <th scope="col" className="px-3 py-2.5 font-semibold">{t.preparacion}</th>
                  <th scope="col" className="px-3 py-2.5 font-semibold">{t.brechas}</th>
                  <th scope="col" className="py-2.5 pl-3 pr-5 font-semibold">{t.acciones}</th>
                </tr>
              </thead>
              <tbody>
                {pagina.data.map((e) => (
                  <tr key={e.id} className="border-t border-border">
                    <td className="py-3 pl-5 pr-3 font-semibold">
                      {e.codigo}
                      {e.es_vigente && <span className="ml-2 text-xs font-normal text-muted">{t.vigente}</span>}
                    </td>
                    <td className="px-3 py-3 tabular-nums">{e.fecha.slice(0, 10)}</td>
                    <td className="px-3 py-3">{e.colaborador.nombre}</td>
                    <td className="px-3 py-3">{e.puesto_objetivo.nombre}</td>
                    <td className="px-3 py-3"><PreparacionBadge valor={e.preparacion} /></td>
                    <td className="px-3 py-3 tabular-nums">{e.brechas}</td>
                    <td className="py-3 pl-3 pr-5">
                      <Link
                        to={`/adecuacion/evaluaciones/${e.id}`}
                        aria-label={t.verAria(e.codigo)}
                        className="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 font-semibold text-text no-underline hover:bg-soft"
                      >
                        {t.ver}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination
            page={pagina.meta.page}
            lastPage={pagina.meta.last_page}
            total={pagina.meta.total}
            from={(pagina.meta.page - 1) * pagina.meta.per_page + 1}
            to={Math.min(pagina.meta.total, pagina.meta.page * pagina.meta.per_page)}
            onPageChange={(page) => cambiar({ page: String(page) })}
          />
        </>
      )}
    </div>
  )
}
