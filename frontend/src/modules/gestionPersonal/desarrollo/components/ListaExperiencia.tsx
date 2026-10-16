import { Button } from '../../../../shared/ui/Button'
import type { Experiencia } from '../api'
import { formatearFecha } from '../fecha'
import { desarrolloText } from '../text'

const text = desarrolloText.experiencia

interface ListaExperienciaProps {
  experiencias: Experiencia[]
  editable: boolean
  onAgregar: () => void
  onEditar: (experiencia: Experiencia) => void
}

/** Una fecha final ausente es «No registrado»: nunca se interpreta como puesto vigente. */
export function ListaExperiencia({ experiencias, editable, onAgregar, onEditar }: ListaExperienciaProps) {
  const noRegistrado = <span className="text-muted">{desarrolloText.notRegistered}</span>

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

      {experiencias.length === 0 && <p className="text-muted">{text.empty}</p>}

      {experiencias.map((experiencia) => (
        <article key={experiencia.id} className="flex flex-wrap justify-between gap-3 border-t border-border pt-4">
          <div className="min-w-0 flex-1 basis-75">
            <h3 className="text-[15px] font-semibold">{experiencia.cargo}</h3>
            <p className="text-sm text-muted">{experiencia.organizacion}</p>
            <p className="mt-1 text-sm">
              {desarrolloText.dates.start}: {formatearFecha(experiencia.fecha_inicio)} · {desarrolloText.dates.end}:{' '}
              {experiencia.fecha_finalizacion ? formatearFecha(experiencia.fecha_finalizacion) : noRegistrado}
            </p>
            <p className="mt-1 text-sm">
              <span className="text-muted">{text.functions}: </span>
              {experiencia.funciones ?? noRegistrado}
            </p>
            <p className="mt-0.5 text-sm">
              <span className="text-muted">{text.notes}: </span>
              {experiencia.observaciones ?? noRegistrado}
            </p>
          </div>
          {editable && (
            <div>
              <Button variant="secondary" aria-label={text.editOf(experiencia.cargo)} onClick={() => onEditar(experiencia)}>
                {text.edit}
              </Button>
            </div>
          )}
        </article>
      ))}
    </section>
  )
}
