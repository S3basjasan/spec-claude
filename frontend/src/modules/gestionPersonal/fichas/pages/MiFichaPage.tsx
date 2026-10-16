import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Badge } from '../../../../shared/ui/Badge'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { BotonExportarFicha } from '../../exportacion/components/BotonExportarFicha'
import { FichaContenido } from '../components/FichaContenido'
import { useMiFicha } from '../hooks/useColaboradores'
import { fichasText } from '../text'

const text = fichasText.myRecord

/** Consulta de la ficha propia, solo lectura. El servidor decide si hay asociación y permiso. */
export function MiFichaPage() {
  const consulta = useMiFicha()
  const [exportable, setExportable] = useState(true)

  let contenido
  if (consulta.isPending) {
    contenido = (
      <div role="status" aria-label={fichasText.detail.loading} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2, 3].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  } else if (consulta.isError) {
    const api = toApiError(consulta.error)
    if (api.code === 'CUENTA_SIN_FICHA') {
      contenido = (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconFileText size={24} />} title={text.noRecordTitle} description={text.noRecordDescription} />
        </div>
      )
    } else if (api.code === 'SIN_PERMISO_FICHA') {
      contenido = (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconFileText size={24} />} title={text.noPermissionTitle} description={text.noPermissionDescription} />
        </div>
      )
    } else {
      contenido = (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState
            title={fichasText.detail.errorTitle}
            description={fichasText.detail.errorDescription}
            retryLabel={fichasText.retry}
            onRetry={() => void consulta.refetch()}
          />
        </div>
      )
    }
  } else {
    const ficha = consulta.data
    const estado = ficha.laboral.estado === null ? null : (fichasText.statuses[ficha.laboral.estado] ?? ficha.laboral.estado)
    contenido = (
      <>
        <p className="flex flex-wrap items-center gap-3 text-base text-muted">
          <span className="text-lg font-bold text-text">{`${ficha.nombres} ${ficha.apellidos}`}</span>
          <span className="font-semibold">{ficha.codigo}</span>
          {estado !== null && <Badge variant="neutral">{estado}</Badge>}
        </p>
        {ficha.capacidades.exportar && exportable && (
          <BotonExportarFicha colaboradorId={null} codigo={ficha.codigo} onSinPermiso={() => setExportable(false)} />
        )}
        <FichaContenido ficha={ficha} esMiFicha onReintentar={() => void consulta.refetch()} />
      </>
    )
  }

  return (
    <div className="mx-auto flex max-w-225 flex-col gap-5">
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
        <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
      </div>
      {contenido}
    </div>
  )
}
