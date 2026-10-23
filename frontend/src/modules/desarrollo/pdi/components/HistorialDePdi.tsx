import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { fechaConHora } from '../fecha'
import { useHistorialPdi } from '../hooks/usePdi'
import { pdiText } from '../text'

const t = pdiText.historial

/** Historial de solo lectura del plan: fecha, usuario, evento, valores anterior y nuevo, y motivo. */
export function HistorialDePdi({ pdiId }: { pdiId: number }) {
  const [pagina, setPagina] = useState(1)
  const consulta = useHistorialPdi(pdiId, pagina)

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={pdiText.loading} className="flex flex-col gap-2">
        <Skeleton className="h-10" />
        <Skeleton className="h-10" />
      </div>
    )
  }
  if (consulta.isError) return <Alert>{t.error}</Alert>
  if (consulta.data.data.length === 0) return <p className="m-0 text-muted">{t.vacio}</p>

  const { data, meta } = consulta.data
  const desde = (meta.page - 1) * meta.per_page + 1

  return (
    <div className="flex flex-col gap-3">
      <div className="overflow-x-auto rounded-xl border border-border">
        <table className="w-full border-collapse text-left text-[14px]">
          <caption className="sr-only">{t.titulo}</caption>
          <thead className="bg-bg text-[13px] text-muted">
            <tr>
              {Object.values(t.columnas).map((columna) => (
                <th key={columna} scope="col" className="px-3 py-2 font-semibold">
                  {columna}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.map((h) => (
              <tr key={h.id} className="border-t border-border">
                <td className="whitespace-nowrap px-3 py-2">{fechaConHora(h.fecha)}</td>
                <td className="px-3 py-2">{h.usuario}</td>
                <td className="px-3 py-2">{h.tipo_etiqueta}</td>
                <td className="px-3 py-2">{h.valor_anterior ?? pdiText.sinDato}</td>
                <td className="px-3 py-2">{h.valor_nuevo ?? pdiText.sinDato}</td>
                <td className="px-3 py-2">{h.motivo ?? pdiText.sinDato}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Pagination page={meta.page} lastPage={meta.last_page} total={meta.total} from={desde} to={desde + data.length - 1} onPageChange={setPagina} />
    </div>
  )
}
