import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconPlayerPlay } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { Tabs } from '../../../../shared/ui/Tabs'
import { useState } from 'react'
import type { FilaMicro } from '../api'
import { AvisoConocimientos } from '../components/AvisoConocimientos'
import { ResumenAprendizaje } from '../components/ResumenAprendizaje'
import { TarjetaAprendizaje } from '../components/TarjetaAprendizaje'
import { useListadoParams, type PestanaAprendizaje } from '../hooks/useListadoParams'
import { useDescargarCertificado } from '../hooks/useMutacionesMicro'
import { useMiAprendizaje } from '../hooks/useMicros'
import { microText } from '../text'

const text = microText.colab

/** «Mi aprendizaje» (colaborador): resumen, pestañas Asignado y Completado y descarga de certificados. */
export function MiAprendizajePage() {
  const { tab, update } = useListadoParams()
  const consulta = useMiAprendizaje()
  const descargar = useDescargarCertificado()
  const [errorCertificado, setErrorCertificado] = useState(false)

  const bajarCertificado = (micro: FilaMicro) => {
    setErrorCertificado(false)
    descargar.mutate({ id: micro.id, codigo: micro.codigo }, { onError: () => setErrorCertificado(true) })
  }

  const cuerpo = () => {
    if (consulta.isPending) {
      return (
        <div role="status" aria-label={microText.loadingLabel} className="flex flex-col gap-4">
          <div className="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(180px,1fr))]">
            {[0, 1, 2, 3].map((n) => (
              <Skeleton key={n} className="h-24" />
            ))}
          </div>
          <Skeleton className="h-72" />
        </div>
      )
    }

    if (consulta.isError) {
      // Sin ficha de colaborador asociada a la cuenta, el servidor responde 403.
      if (toApiError(consulta.error).status === 403) {
        return <EmptyState icon={<IconPlayerPlay size={24} />} title={microText.errorTitle} description={text.noFicha} />
      }
      return <ErrorState title={microText.errorTitle} description={microText.errorDescription} retryLabel={microText.retry} onRetry={() => void consulta.refetch()} />
    }

    const { resumen, asignados, completados, avisos } = consulta.data
    const lista = tab === 'completado' ? completados : asignados

    return (
      <>
        {(avisos ?? []).map((aviso) => (
          <Alert key={aviso} tone="neutral">
            {text.warnings[aviso] ?? aviso}
          </Alert>
        ))}
        <ResumenAprendizaje resumen={resumen} />
        <Tabs
          label={text.tabsLabel}
          idBase="micro"
          active={tab}
          onChange={(id) => update({ tab: id as PestanaAprendizaje })}
          tabs={[
            { id: 'asignado', label: text.tabAssigned(asignados.length) },
            { id: 'completado', label: text.tabDone(completados.length) },
          ]}
        >
          {errorCertificado && <Alert>{text.certificateFailed}</Alert>}
          {lista.length === 0 ? (
            <div className="rounded-xl border border-border bg-surface">
              <EmptyState
                icon={<IconPlayerPlay size={24} />}
                title={tab === 'completado' ? text.emptyDone : text.emptyAssigned}
                description={tab === 'completado' ? text.emptyDoneDescription : text.emptyAssignedDescription}
              />
            </div>
          ) : (
            <ul className="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(280px,1fr))]">
              {lista.map((micro) => (
                <li key={micro.id} className="flex">
                  <div className="flex w-full">
                    <TarjetaAprendizaje micro={micro} pestana={tab} onCertificado={bajarCertificado} descargando={descargar.isPending} />
                  </div>
                </li>
              ))}
            </ul>
          )}
        </Tabs>
      </>
    )
  }

  return (
    <div className="mx-auto flex max-w-300 flex-col gap-5">
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
        <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
      </div>
      {cuerpo()}
    </div>
  )
}
