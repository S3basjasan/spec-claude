import { Link } from 'react-router-dom'
import { Badge } from '../../../../shared/ui/Badge'
import type { PoliticaResumen, SituacionPolitica } from '../api'
import { politicasText } from '../text'
import { formatearInstanteLocal } from '../validation'

const text = politicasText.list

const VARIANTES: Record<SituacionPolitica, 'default' | 'neutral' | 'warning'> = {
  SinConfigurar: 'warning',
  Vigente: 'default',
  NoAdmiteSolicitudes: 'neutral',
}

/** Un registro por tipo: situación, disponibilidad, versión vigente, vigencia, programada y borrador. */
export function TablaDePoliticas({ filas }: { filas: PoliticaResumen[] }) {
  return (
    <div role="region" aria-label={text.tableLabel} tabIndex={0} className="relative overflow-x-auto rounded-xl border border-border bg-surface">
      <table className="w-full min-w-200 border-collapse text-left text-[15px]">
        <caption className="sr-only">{text.tableLabel}</caption>
        <thead>
          <tr className="border-b border-border text-sm text-muted">
            {(['tipo', 'situacion', 'disponible', 'vigente', 'desde', 'programada', 'borrador'] as const).map((columna) => (
              <th key={columna} scope="col" className="px-4 py-3 font-semibold">
                {text.columns[columna]}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {filas.map((fila) => {
            const nombre = politicasText.tipos[fila.tipo]
            return (
              <tr key={fila.tipo} className="border-b border-border last:border-b-0">
                <th scope="row" className="px-4 py-3 text-left font-semibold">
                  <Link to={`/politicas-personal/${fila.tipo}`} aria-label={text.open(nombre)} className="text-accent underline">
                    {nombre}
                  </Link>
                </th>
                <td className="px-4 py-3">
                  <Badge variant={VARIANTES[fila.situacion]}>{politicasText.situaciones[fila.situacion]}</Badge>
                </td>
                <td className="px-4 py-3">{fila.disponible === null ? politicasText.none : fila.disponible ? text.available : text.notAvailable}</td>
                <td className="px-4 py-3 whitespace-nowrap">{fila.version_vigente ? text.version(fila.version_vigente.numero) : politicasText.none}</td>
                <td className="px-4 py-3 whitespace-nowrap">{formatearInstanteLocal(fila.version_vigente?.inicio_vigencia_local ?? null)}</td>
                <td className="px-4 py-3 whitespace-nowrap">
                  {fila.version_programada
                    ? `${text.version(fila.version_programada.numero)} · ${formatearInstanteLocal(fila.version_programada.inicio_vigencia_local)}`
                    : politicasText.none}
                </td>
                <td className="px-4 py-3">{fila.tiene_borrador ? text.hasDraft : politicasText.none}</td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
