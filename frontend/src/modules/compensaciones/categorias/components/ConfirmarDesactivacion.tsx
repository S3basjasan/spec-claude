import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import type { Categoria } from '../api'
import { useCambiarEstadoCategoria } from '../hooks/useGuardarCategoria'
import { categoriasReconocimientoText } from '../text'

const text = categoriasReconocimientoText.confirm

interface ConfirmarDesactivacionProps {
  /** Categoría vigente; su versión se lee en el momento de confirmar. */
  categoria: Categoria
  onCancelar: () => void
  onConfirmado: (categoria: Categoria) => void
  /** Con conflicto: vuelve a consultar el listado para mostrar la versión vigente. */
  onActualizar: () => void
}

/** Desactivar pide confirmación e informa que la categoría no podrá usarse en nuevos hitos ni desafíos. */
export function ConfirmarDesactivacion({ categoria, onCancelar, onConfirmado, onActualizar }: ConfirmarDesactivacionProps) {
  const cambiar = useCambiarEstadoCategoria()
  const [error, setError] = useState<string | undefined>(undefined)

  const confirmar = () => {
    if (cambiar.isPending) return
    setError(undefined)
    cambiar.mutate(
      { id: categoria.id, version: categoria.updated_at, estado: 'Inactivo' },
      {
        onSuccess: onConfirmado,
        onError: (e) => {
          const api = toApiError(e)
          if (api.status === 409) {
            setError(text.conflict)
            onActualizar()
          } else if (api.status === 404) setError(text.notFound)
          else setError(text.failed)
        },
      },
    )
  }

  return (
    <ConfirmDialog
      open
      title={text.deactivateTitle}
      description={text.deactivateDescription(categoria.nombre)}
      confirmLabel={text.deactivateAction}
      cancelLabel={text.cancel}
      busy={cambiar.isPending}
      error={error}
      onConfirm={confirmar}
      onCancel={onCancelar}
    />
  )
}
