import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import type { Documento } from '../api'
import { useArchivarDocumento } from '../hooks/useGuardarDocumento'
import { documentosText } from '../text'

const text = documentosText.confirm

interface ConfirmarArchivoProps {
  colaboradorId: number
  /** Documento vigente; su versión se lee en el momento de confirmar. */
  documento: Documento
  onCancelar: () => void
  onArchivado: () => void
  /** Con conflicto: vuelve a consultar la lista para mostrar la versión vigente. */
  onActualizar: () => void
}

export function ConfirmarArchivo({ colaboradorId, documento, onCancelar, onArchivado, onActualizar }: ConfirmarArchivoProps) {
  const archivar = useArchivarDocumento(colaboradorId)
  const [error, setError] = useState<string | undefined>(undefined)

  const confirmar = () => {
    if (archivar.isPending) return
    setError(undefined)
    archivar.mutate(
      { id: documento.id, version: documento.version },
      {
        onSuccess: onArchivado,
        onError: (e) => {
          const api = toApiError(e)
          if (api.status === 409) {
            setError(text.changed)
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
      title={text.archiveTitle}
      description={text.archiveDescription(documento.titulo)}
      confirmLabel={text.archiveAction}
      cancelLabel={text.cancel}
      tone="primary"
      busy={archivar.isPending}
      error={error}
      onConfirm={confirmar}
      onCancel={onCancelar}
    />
  )
}
