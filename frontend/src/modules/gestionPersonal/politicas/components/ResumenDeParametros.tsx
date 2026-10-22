import type { Parametros, Tarea } from '../api'
import { politicasText } from '../text'

const text = politicasText

function valorLegible(clave: string, valor: unknown): string {
  if (typeof valor === 'boolean') return valor ? text.yes : text.no
  if (typeof valor === 'number') return `${valor} ${text.params.daysUnit}`
  if (typeof valor === 'string') return valor
  return clave === 'tareas' ? '' : text.none
}

interface ResumenDeParametrosProps {
  parametros: Parametros
  /** Etiqueta accesible del grupo. */
  label: string
}

/** Parámetros de una versión en lectura: etiqueta y valor; las tareas de Offboarding como lista numerada. */
export function ResumenDeParametros({ parametros, label }: ResumenDeParametrosProps) {
  const claves = Object.keys(parametros)
  if (claves.length === 0) return <p className="text-sm text-muted">{text.none}</p>

  return (
    <dl aria-label={label} className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
      {claves.map((clave) => {
        const valor = parametros[clave]
        const etiqueta = text.parametersLabels[clave] ?? clave

        if (Array.isArray(valor)) {
          const tareas = valor as Tarea[]
          return (
            <div key={clave} className="sm:col-span-2">
              <dt className="text-sm font-semibold text-muted">{etiqueta}</dt>
              <dd>
                {tareas.length === 0 ? (
                  <p>{text.params.tasks.empty}</p>
                ) : (
                  <ol className="mt-1 list-decimal space-y-1 pl-5">
                    {tareas.map((tarea, i) => (
                      <li key={`${tarea.nombre}-${i}`}>
                        <span className="font-semibold">{tarea.nombre}</span>
                        {tarea.exige_evidencia && <span className="ml-2 text-sm text-muted">({text.applicable.evidenceRequired})</span>}
                        {tarea.descripcion && <p className="text-sm text-muted">{tarea.descripcion}</p>}
                      </li>
                    ))}
                  </ol>
                )}
              </dd>
            </div>
          )
        }

        return (
          <div key={clave}>
            <dt className="text-sm font-semibold text-muted">{etiqueta}</dt>
            <dd>{valorLegible(clave, valor)}</dd>
          </div>
        )
      })}
    </dl>
  )
}
