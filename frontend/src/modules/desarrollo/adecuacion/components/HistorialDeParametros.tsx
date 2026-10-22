import type { EntradaDeHistorialDeParametros } from '../api'
import { adecuacionText } from '../text'

const t = adecuacionText.parametros

/** Historial de cambios de los parámetros: usuario, fecha, valor anterior y valor nuevo (solo lectura). */
export function HistorialDeParametros({ items }: { items: EntradaDeHistorialDeParametros[] }) {
  return (
    <section aria-labelledby="historial-parametros-titulo" className="overflow-x-auto rounded-xl border border-border bg-surface">
      <h2 id="historial-parametros-titulo" className="m-0 px-5 pb-2 pt-4 text-lg font-semibold">
        {t.historialTitulo}
      </h2>

      {items.length === 0 ? (
        <p className="m-0 px-5 pb-5 text-sm text-muted">{t.historialVacio}</p>
      ) : (
        <table className="w-full min-w-[560px] border-collapse text-sm">
          <thead>
            <tr className="text-left text-[13px] text-muted">
              <th scope="col" className="py-2.5 pl-5 pr-3 font-semibold">{t.fecha}</th>
              <th scope="col" className="px-3 py-2.5 font-semibold">{t.usuario}</th>
              <th scope="col" className="px-3 py-2.5 font-semibold">{t.campo}</th>
              <th scope="col" className="px-3 py-2.5 font-semibold">{t.anterior}</th>
              <th scope="col" className="py-2.5 pl-3 pr-5 font-semibold">{t.nuevo}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((h, i) => (
              <tr key={`${h.fecha}-${h.campo}-${i}`} className="border-t border-border">
                <td className="py-3 pl-5 pr-3 tabular-nums">{h.fecha.slice(0, 16)}</td>
                <td className="px-3 py-3">{h.usuario.nombre ?? t.sinUsuario}</td>
                <td className="px-3 py-3">{t.campos[h.campo]}</td>
                <td className="px-3 py-3 tabular-nums">{h.valor_anterior}</td>
                <td className="py-3 pl-3 pr-5 font-semibold tabular-nums">{h.valor_nuevo}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  )
}
