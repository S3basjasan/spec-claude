import { useMemo } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconUsers } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { MIN_CANDIDATOS, type ConsultaComparacion, type PesosConsulta } from '../api'
import { useOpcionesGenerales } from '../hooks/useAdecuacion'
import { pesosParaEnviar, useComparacion, useEstadoDeComparacion } from '../hooks/useComparacion'
import { adecuacionText } from '../text'
import { PanelDePesosYPuesto } from './PanelDePesosYPuesto'
import { SelectorDeCandidatos } from './SelectorDeCandidatos'
import { TablaDeRanking } from './TablaDeRanking'

const t = adecuacionText.comparar

/** Pestaña «Comparar candidatos»: puesto, candidatos y pesos de la consulta; el ranking se calcula al vuelo y no se guarda. */
export function PestanaComparar() {
  const opciones = useOpcionesGenerales()
  const estado = useEstadoDeComparacion()

  // Se lee del ranking anterior (placeholder) para no perder los pesos guardados al editar uno.
  const guardados = useRespaldoDePesos(estado.puesto, estado.candidatos)
  const evaluados = pesosParaEnviar(estado.pesos, guardados)

  const consulta = useMemo<ConsultaComparacion | null>(() => {
    if (estado.puesto === null || estado.candidatos.length < MIN_CANDIDATOS || evaluados.error !== undefined) return null

    return { puesto_objetivo_id: estado.puesto, colaborador_ids: estado.candidatos, ...(evaluados.pesos === undefined ? {} : { pesos: evaluados.pesos }) }
  }, [estado.puesto, estado.candidatos, evaluados.error, evaluados.pesos])

  const comparacion = useComparacion(consulta)
  const datos = comparacion.data

  const texto = (dimension: 'experiencia' | 'conocimientos' | 'competencias', clave: 'Experiencia' | 'Conocimiento' | 'Competencia') =>
    estado.pesos[dimension] ?? (datos === undefined ? '' : String(datos.pesos[clave]))

  return (
    <div className="flex flex-col gap-5">
      <PanelDePesosYPuesto
        puestos={opciones.data?.puestos ?? []}
        puesto={estado.puesto}
        onPuesto={estado.elegirPuesto}
        valores={{
          experiencia: texto('experiencia', 'Experiencia'),
          conocimientos: texto('conocimientos', 'Conocimiento'),
          competencias: texto('competencias', 'Competencia'),
        }}
        onPeso={estado.editarPeso}
        suma={evaluados.suma}
        error={evaluados.error === 'suma' || evaluados.error === 'entero' ? evaluados.error : undefined}
        hayPesosEditados={estado.hayPesosEditados}
        onRestablecer={estado.restablecerPesos}
      />

      <SelectorDeCandidatos colaboradores={opciones.data?.colaboradores ?? []} elegidos={estado.candidatos} onAlternar={estado.alternarCandidato} />

      {opciones.isError && (
        <ErrorState title={adecuacionText.errores.cargar.title} description={adecuacionText.errores.cargar.description} retryLabel={adecuacionText.reintentar} onRetry={() => void opciones.refetch()} />
      )}

      {consulta === null && <EmptyState icon={<IconUsers />} title={t.vacio} description="" />}

      {consulta !== null && comparacion.isPending && (
        <div role="status" aria-label={adecuacionText.loading} className="flex flex-col gap-2">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      )}

      {consulta !== null && comparacion.isError && <ErrorDeComparacion error={comparacion.error} onReintentar={() => void comparacion.refetch()} />}

      {consulta !== null && datos !== undefined && !comparacion.isError && (
        <>
          {datos.fuentes_no_disponibles.length > 0 && <Alert tone="neutral">{adecuacionText.errores.fuentes}</Alert>}
          <TablaDeRanking comparacion={datos} />
        </>
      )}
    </div>
  )
}

/** Pesos guardados: se toman de la última respuesta sin pesos editados para completar los que el usuario no tocó. */
function useRespaldoDePesos(puesto: number | null, candidatos: number[]): PesosConsulta | undefined {
  const sinPesos = useComparacion(puesto !== null && candidatos.length >= MIN_CANDIDATOS ? { puesto_objetivo_id: puesto, colaborador_ids: candidatos } : null)
  const pesos = sinPesos.data?.pesos

  return pesos === undefined ? undefined : { competencias: pesos.Competencia, conocimientos: pesos.Conocimiento, experiencia: pesos.Experiencia }
}

function ErrorDeComparacion({ error, onReintentar }: { error: unknown; onReintentar: () => void }) {
  const e = toApiError(error)

  if (e.status === 422 && e.message !== undefined) return <Alert>{e.message}</Alert>

  return <ErrorState title={adecuacionText.errores.cargar.title} description={adecuacionText.errores.cargar.description} retryLabel={adecuacionText.reintentar} onRetry={onReintentar} />
}
