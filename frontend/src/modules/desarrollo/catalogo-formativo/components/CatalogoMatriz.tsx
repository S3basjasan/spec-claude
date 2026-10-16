import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import type { FilaCatalogo } from '../api'
import { catalogoText } from '../text'

interface CatalogoMatrizProps {
  items: FilaCatalogo[]
  onVerDetalle: (id: number) => void
}

const text = catalogoText.admin

/** Matriz formativa: una fila por competencia con su área y la cantidad de cursos y micro-contenidos disponibles. */
export function CatalogoMatriz({ items, onVerDetalle }: CatalogoMatrizProps) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface">
      <table aria-label={text.listLabel} className="w-full min-w-160 border-collapse text-left">
        <thead>
          <tr className="text-[13px] font-semibold text-muted">
            <th scope="col" className="px-5 py-3.5">
              {text.columns.competence}
            </th>
            <th scope="col" className="px-5 py-3.5">
              {text.columns.type}
            </th>
            <th scope="col" className="px-5 py-3.5">
              {text.columns.area}
            </th>
            <th scope="col" className="px-5 py-3.5">
              {text.columns.courses}
            </th>
            <th scope="col" className="px-5 py-3.5">
              {text.columns.micro}
            </th>
            <th scope="col" className="px-5 py-3.5 text-right">
              {text.columns.actions}
            </th>
          </tr>
        </thead>
        <tbody>
          {items.map((fila) => (
            <tr key={fila.competencia_id} className="border-t border-border text-[15px] hover:bg-soft">
              <td className="px-5 py-3.5">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="font-semibold">{fila.nombre}</span>
                  {fila.estado === 'Inactivo' && <Badge variant="neutral">{catalogoText.estadoCompetencia.Inactivo}</Badge>}
                </div>
                <span className="text-[13px] text-muted">{fila.codigo}</span>
              </td>
              <td className="px-5 py-3.5">
                <Badge>{catalogoText.tipoCompetencia[fila.tipo]}</Badge>
              </td>
              <td className="px-5 py-3.5">
                {fila.area_formativa ? fila.area_formativa.nombre : <span className="text-muted">{catalogoText.sinArea}</span>}
              </td>
              <td className="px-5 py-3.5">
                {fila.cursos_count === 0 ? <span className="text-muted">{text.noCourses}</span> : catalogoText.cursos(fila.cursos_count)}
              </td>
              <td className="px-5 py-3.5">
                {fila.microcontenidos_count === 0 ? (
                  <span className="text-muted">{text.noMicro}</span>
                ) : (
                  catalogoText.microcontenidos(fila.microcontenidos_count)
                )}
              </td>
              <td className="px-5 py-3.5 text-right">
                <Button variant="secondary" aria-label={text.viewDetailOf(fila.nombre)} onClick={() => onVerDetalle(fila.competencia_id)}>
                  {text.viewDetail}
                </Button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
