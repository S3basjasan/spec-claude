import { useState } from 'react'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useParametrosDeAdecuacion } from '../hooks/useParametros'
import { adecuacionText } from '../text'
import { FormularioDeParametros } from './FormularioDeParametros'
import { HistorialDeParametros } from './HistorialDeParametros'

/** Pestaña «Parámetros»: pesos por dimensión y umbral de preparación, con su historial de cambios. */
export function PestanaParametros() {
  const [pagina, setPagina] = useState(1)
  const consulta = useParametrosDeAdecuacion(pagina)
  const datos = consulta.data

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={adecuacionText.loading} className="flex flex-col gap-3">
        <Skeleton className="h-48 w-full" />
        <Skeleton className="h-32 w-full" />
      </div>
    )
  }

  if (consulta.isError || datos === undefined) {
    return (
      <ErrorState title={adecuacionText.errores.cargar.title} description={adecuacionText.errores.cargar.description} retryLabel={adecuacionText.reintentar} onRetry={() => void consulta.refetch()} />
    )
  }

  return (
    <div className="flex flex-col gap-5">
      {/* La clave reinicia el borrador cuando el servidor devuelve valores distintos (tras guardar). */}
      <FormularioDeParametros key={`${datos.pesos.Competencia}-${datos.pesos.Conocimiento}-${datos.pesos.Experiencia}-${datos.umbral_meses}`} parametros={datos} />
      <HistorialDeParametros items={datos.historial} />
      {datos.meta.last_page > 1 && (
        <Pagination
          page={datos.meta.page}
          lastPage={datos.meta.last_page}
          total={datos.meta.total}
          from={(datos.meta.page - 1) * datos.meta.per_page + 1}
          to={Math.min(datos.meta.total, datos.meta.page * datos.meta.per_page)}
          onPageChange={setPagina}
        />
      )}
    </div>
  )
}
