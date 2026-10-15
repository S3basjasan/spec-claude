import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { UnidadResumen } from '../api'
import { useCambiarEstadoUnidad } from '../hooks/useCambiarEstadoUnidad'
import { useImpactoDeDesactivacion } from '../hooks/useUnidades'
import { unidadesText } from '../text'

const text = unidadesText.deactivate

interface DesactivarModalProps {
  unidad: UnidadResumen
  onCerrar: () => void
  /** Se llama con la cantidad total de unidades desactivadas. */
  onDesactivada: (cantidad: number) => void
}

/**
 * Diálogo de confirmación de la desactivación en cascada: nombra la unidad, lista las dependientes activas que también se
 * desactivarán y, si hay posiciones Ocupadas o Vacantes en la rama, explica el bloqueo y no permite confirmar.
 */
export function DesactivarModal({ unidad, onCerrar, onDesactivada }: DesactivarModalProps) {
  const impacto = useImpactoDeDesactivacion(unidad.id)
  const cambio = useCambiarEstadoUnidad()
  const [error, setError] = useState<string | undefined>()

  const dependientes = impacto.data?.dependientes_activas ?? []
  const bloqueos = impacto.data?.unidades_con_posiciones ?? []
  const total = 1 + dependientes.length

  const confirmar = () => {
    if (cambio.isPending) return
    setError(undefined)
    cambio.mutate(
      { id: unidad.id, estado: 'Inactiva' },
      {
        onSuccess: () => onDesactivada(total),
        onError: (fallo) => {
          const api = toApiError(fallo)
          if (api.isNetworkError) return setError(text.networkError)
          if (api.status === 404) return setError(unidadesText.list.notFound)
          setError(api.status === 422 ? (api.fieldErrors.estado?.[0] ?? api.message ?? text.unexpectedError) : text.unexpectedError)
        },
      },
    )
  }

  return (
    <Modal
      open
      title={text.title(unidad.nombre)}
      description={text.description}
      onClose={onCerrar}
      busy={cambio.isPending}
      closeLabel={text.close}
      footer={
        <>
          <Button variant="secondary" onClick={onCerrar} disabled={cambio.isPending}>
            {text.cancel}
          </Button>
          <Button variant="danger" onClick={confirmar} loading={cambio.isPending} disabled={!impacto.data?.procede}>
            {text.confirm(total)}
          </Button>
        </>
      }
    >
      {impacto.isPending && (
        <div role="status" aria-label={text.loadingLabel} className="flex flex-col gap-2">
          <Skeleton className="h-5" />
          <Skeleton className="h-5 w-2/3" />
        </div>
      )}
      {impacto.isError && (
        <Alert>
          <p>{text.loadError}</p>
          <Button variant="secondary" className="mt-2" onClick={() => void impacto.refetch()}>
            {text.retry}
          </Button>
        </Alert>
      )}
      {bloqueos.length > 0 && (
        <Alert>
          <p>{text.blocked}</p>
          <ul className="mt-2 list-disc pl-5">
            {bloqueos.map((u) => (
              <li key={u.id}>{text.blockedItem(u.codigo, u.nombre, u.cantidad)}</li>
            ))}
          </ul>
        </Alert>
      )}
      {bloqueos.length === 0 && dependientes.length > 0 && (
        <div>
          <p className="text-sm font-semibold">{text.withDependents(dependientes.length)}</p>
          <ul className="mt-2 list-disc pl-5 text-sm">
            {dependientes.map((u) => (
              <li key={u.id}>
                {u.codigo} {u.nombre} <span className="text-muted">({u.tipo})</span>
              </li>
            ))}
          </ul>
        </div>
      )}
      {error && <Alert>{error}</Alert>}
    </Modal>
  )
}
