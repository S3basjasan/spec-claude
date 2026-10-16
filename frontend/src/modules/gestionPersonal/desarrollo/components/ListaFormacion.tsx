import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import type { Formacion } from '../api'
import { formatearFecha } from '../fecha'
import { desarrolloText } from '../text'

const text = desarrolloText.formacion

interface ListaFormacionProps {
  formaciones: Formacion[]
  /** `acciones.editar` del servidor: sin él no hay ningún control de escritura. */
  editable: boolean
  onAgregar: () => void
  onEditar: (formacion: Formacion) => void
}

function Fechas({ inicio, fin }: { inicio: string | null; fin: string | null }) {
  const noRegistrado = <span className="text-muted">{desarrolloText.notRegistered}</span>
  return (
    <p className="mt-1 text-sm">
      {desarrolloText.dates.start}: {inicio ? formatearFecha(inicio) : noRegistrado} · {desarrolloText.dates.end}:{' '}
      {fin ? formatearFecha(fin) : noRegistrado}
    </p>
  )
}

export function ListaFormacion({ formaciones, editable, onAgregar, onEditar }: ListaFormacionProps) {
  return (
    <section aria-label={text.listLabel} className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-base font-bold">{text.title}</h2>
        {editable && (
          <Button variant="secondary" onClick={onAgregar}>
            {text.add}
          </Button>
        )}
      </div>

      {formaciones.length === 0 && <p className="text-muted">{text.empty}</p>}

      {formaciones.map((formacion) => (
        <article key={formacion.id} className="flex flex-wrap justify-between gap-3 border-t border-border pt-4">
          <div className="min-w-0 flex-1 basis-75">
            <h3 className="text-[15px] font-semibold">{formacion.titulo_programa}</h3>
            <p className="text-sm text-muted">
              {formacion.nivel} · {formacion.institucion}
            </p>
            <Fechas inicio={formacion.fecha_inicio} fin={formacion.fecha_finalizacion} />
            <p className="mt-1 text-sm text-muted">{formacion.observaciones ?? desarrolloText.notRegistered}</p>
          </div>
          <div className="flex items-start gap-2">
            <Badge variant={formacion.estado === 'Incompleta' ? 'neutral' : 'default'}>{desarrolloText.estadosFormacion[formacion.estado]}</Badge>
            {editable && (
              <Button variant="secondary" aria-label={text.editOf(formacion.titulo_programa)} onClick={() => onEditar(formacion)}>
                {text.edit}
              </Button>
            )}
          </div>
        </article>
      ))}
    </section>
  )
}
