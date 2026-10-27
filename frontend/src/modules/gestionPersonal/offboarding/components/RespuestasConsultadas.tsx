import { Alert } from '../../../../shared/ui/Alert'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { problemaDeSalida } from '../errores'
import { useRespuestasConsultadas } from '../hooks/useOffboarding'
import { offboardingText } from '../text'

const text = offboardingText.interview

/**
 * Respuestas originales de una entrevista completada. Se piden solo al abrir esta sección porque cada consulta queda
 * registrada; sin el permiso específico se explica la negativa (no se muestra como lista vacía).
 */
export function RespuestasConsultadas({ id }: { id: number }) {
  const consulta = useRespuestasConsultadas(id, true)

  if (consulta.isPending) return <Skeleton className="h-24" />
  if (consulta.isError) {
    const problema = problemaDeSalida(consulta.error)
    return <Alert>{problema.codigo === 'SIN_PERMISO_DE_ENTREVISTAS' ? text.noPermission : text.answersError}</Alert>
  }

  const datos = consulta.data
  if (!datos.disponible || datos.respuestas === null) return <Alert tone="neutral">{text.answersNotAvailable}</Alert>

  const { respuestas, cuestionario } = datos
  const dimensiones = Object.entries(cuestionario?.dimensiones ?? {})

  return (
    <section aria-label={text.answersTitle} className="flex flex-col gap-4">
      <p className="text-[13px] text-muted">{text.answersNote}</p>

      <div>
        <h4 className="mb-2 text-sm font-semibold">{text.ratings}</h4>
        <dl className="grid grid-cols-1 gap-2 sm:grid-cols-2">
          {dimensiones.map(([clave, dimension]) => {
            const respuesta = respuestas.valoraciones[clave]
            return (
              <div key={clave} className="flex flex-col gap-0.5 rounded-lg border border-border p-3">
                <dt className="text-[13px] font-semibold text-muted">{dimension.nombre}</dt>
                <dd className="text-[15px]">{respuesta ? `${respuesta.valor} · ${cuestionario?.escala[String(respuesta.valor)] ?? ''}` : text.notAnswered}</dd>
              </div>
            )
          })}
        </dl>
      </div>

      <div>
        <h4 className="mb-2 text-sm font-semibold">{text.declaredReasons}</h4>
        {respuestas.categorias.length === 0 ? (
          <p className="text-sm text-muted">{text.noneDeclared}</p>
        ) : (
          <ul className="flex flex-wrap gap-2">
            {respuestas.categorias.map((categoria) => (
              <li key={categoria.id} className="rounded-full bg-soft px-3 py-1 text-sm">
                {categoria.nombre}
              </li>
            ))}
          </ul>
        )}
      </div>

      <div>
        <h4 className="mb-2 text-sm font-semibold">{text.openAnswers}</h4>
        <ul className="flex flex-col gap-3">
          {Object.entries(cuestionario?.abiertas ?? {}).map(([clave, pregunta]) => (
            <li key={clave} className="rounded-lg border border-border p-3">
              <p className="text-[13px] font-semibold text-muted">{pregunta}</p>
              <p className="whitespace-pre-wrap text-[15px]">{respuestas.abiertas[clave] ?? text.notAnswered}</p>
            </li>
          ))}
        </ul>
      </div>
    </section>
  )
}
