import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import type { Categoria } from '../api'
import { useCambiarEstadoCategoria } from '../hooks/useGuardarCategoria'
import { categoriasText } from '../text'

const text = categoriasText.confirm

interface ConfirmarEstadoCategoriaProps {
  /** Categoría vigente; su versión se lee en el momento de confirmar. */
  categoria: Categoria
  onCancelar: () => void
  onConfirmado: (categoria: Categoria) => void
  /** Con conflicto: vuelve a consultar el catálogo para mostrar la versión vigente. */
  onActualizar: () => void
}

/** Desactivar informa que los documentos se conservan; reactivar confirma sin tono destructivo. */
export function ConfirmarEstadoCategoria({ categoria, onCancelar, onConfirmado, onActualizar }: ConfirmarEstadoCategoriaProps) {
  const cambiar = useCambiarEstadoCategoria()
  const [error, setError] = useState<string | undefined>(undefined)
  const desactivando = categoria.estado === 'Activa'

  const confirmar = () => {
    if (cambiar.isPending) return
    setError(undefined)
    cambiar.mutate(
      { id: categoria.id, version: categoria.version, estado: desactivando ? 'Inactiva' : 'Activa' },
      {
        onSuccess: onConfirmado,
        onError: (e) => {
          const api = toApiError(e)
          if (api.status === 409) {
            setError(text.changed)
            onActualizar()
          } else if (api.status === 404) setError(text.notFound)
          else if (api.code === 'ESCRITURA_DESHABILITADA') setError(text.writeDisabled)
          else setError(text.failed)
        },
      },
    )
  }

  return (
    <ConfirmDialog
      open
      title={desactivando ? text.deactivateTitle : text.reactivateTitle}
      description={desactivando ? text.deactivateDescription(categoria.nombre) : text.reactivateDescription(categoria.nombre)}
      confirmLabel={desactivando ? text.deactivateAction : text.reactivateAction}
      cancelLabel={text.cancel}
      tone="primary"
      busy={cambiar.isPending}
      error={error}
      onConfirm={confirmar}
      onCancel={onCancelar}
    />
  )
}
