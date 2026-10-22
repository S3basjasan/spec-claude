import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconList } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { EventoDeHistorial } from '../api'
import { useHistorialRuta } from '../hooks/useRutas'
import { rutasText } from '../text'

const t = rutasText.historial

/** Resume un valor del historial como «Campo: valor · Campo: valor»; omite el identificador del colaborador (ya figura aparte). */
function resumir(valor: Record<string, unknown> | null): string {
  if (valor === null) return t.sinValor
  const partes = Object.entries(valor)
    .filter(([clave]) => clave !== 'colaborador_id')
    .map(([clave, v]) => `${t.campos[clave] ?? clave}: ${v === null || v === undefined || v === '' ? t.sinValor : String(v)}`)
  return partes.length === 0 ? t.sinValor : partes.join(' · ')
}

function fila(e: EventoDeHistorial) {
  return (
    <tr key={e.id} className="border-t border-border align-top">
      <td className="whitespace-nowrap py-2 pl-4 pr-3">{rutasText.fecha(e.created_at)}</td>
      <td className="px-3 py-2">{e.usuario}</td>
      <td className="px-3 py-2">
        <div className="font-semibold">{t.acciones[e.accion] ?? e.accion}</div>
        {e.colaborador && <div className="text-xs text-muted">{e.colaborador}</div>}
      </td>
      <td className="px-3 py-2 text-muted">{resumir(e.valor_anterior)}</td>
      <td className="px-3 py-2">{resumir(e.valor_nuevo)}</td>
      <td className="py-2 pl-3 pr-4">{e.motivo ?? t.sinValor}</td>
    </tr>
  )
}

interface HistorialDeRutaProps {
  rutaId: number
  pagina: number
  onPagina: (pagina: number) => void
}

/** Historial de solo lectura de la ruta, de sus niveles y de sus inscripciones (pestaña Historial). */
export function HistorialDeRuta({ rutaId, pagina, onPagina }: HistorialDeRutaProps) {
  const historial = useHistorialRuta(rutaId, pagina)

  return (
    <section aria-labelledby="historial-titulo" className="flex flex-col gap-3">
      <div>
        <h2 id="historial-titulo" className="m-0 text-lg">
          {t.title}
        </h2>
        <p className="mt-1 text-[13px] text-muted">{t.descripcion}</p>
      </div>
      {historial.isPending ? (
        <div role="status" aria-label={rutasText.loading} className="flex flex-col gap-2">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-12" />
          ))}
        </div>
      ) : historial.isError ? (
        <ErrorState title={rutasText.error.title} description={rutasText.error.description} retryLabel={rutasText.error.retry} onRetry={() => void historial.refetch()} />
      ) : historial.data.data.length === 0 ? (
        <EmptyState icon={<IconList size={24} />} title={t.vacio} description={t.descripcion} />
      ) : (
        <>
          <div className="overflow-x-auto rounded-xl border border-border bg-surface">
            <table aria-label={t.tabla} className="w-full min-w-210 border-collapse text-sm">
              <thead>
                <tr className="text-left text-[13px] text-muted">
                  <th scope="col" className="py-3 pl-4 pr-3 font-semibold">
                    {t.columnas.fecha}
                  </th>
                  <th scope="col" className="px-3 py-3 font-semibold">
                    {t.columnas.usuario}
                  </th>
                  <th scope="col" className="px-3 py-3 font-semibold">
                    {t.columnas.accion}
                  </th>
                  <th scope="col" className="px-3 py-3 font-semibold">
                    {t.columnas.anterior}
                  </th>
                  <th scope="col" className="px-3 py-3 font-semibold">
                    {t.columnas.nuevo}
                  </th>
                  <th scope="col" className="py-3 pl-3 pr-4 font-semibold">
                    {t.columnas.motivo}
                  </th>
                </tr>
              </thead>
              <tbody>{historial.data.data.map(fila)}</tbody>
            </table>
          </div>
          <Pagination
            page={historial.data.meta.page}
            lastPage={historial.data.meta.last_page}
            total={historial.data.meta.total}
            from={(historial.data.meta.page - 1) * historial.data.meta.per_page + 1}
            to={Math.min(historial.data.meta.page * historial.data.meta.per_page, historial.data.meta.total)}
            onPageChange={onPagina}
          />
        </>
      )}
    </section>
  )
}
