import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import type { PosicionDetalle } from '../api'
import { useSuprimirPosicion } from '../hooks/useSuprimirPosicion'
import { posicionesText } from '../text'

const text = posicionesText.suppressDialog

interface SuprimirDialogProps {
  posicion: PosicionDetalle
  onCerrar: () => void
  onSuprimida: () => void
}

/** Confirmación de la supresión definitiva; si el servidor la rechaza muestra el motivo y sigue abierto. */
export function SuprimirDialog({ posicion, onCerrar, onSuprimida }: SuprimirDialogProps) {
  const suprimir = useSuprimirPosicion()
  const [error, setError] = useState<string | undefined>()

  const confirmar = () => {
    if (suprimir.isPending) return
    setError(undefined)
    suprimir.mutate(posicion.id, {
      onSuccess: onSuprimida,
      onError: (fallo) => {
        const api = toApiError(fallo)
        if (api.isNetworkError) return setError(text.networkError)
        if (api.status === 404) return setError(posicionesText.detail.notFound)
        if (api.status === 422) return setError(api.fieldErrors.estado?.[0] ?? api.message ?? text.unexpectedError)
        setError(text.unexpectedError)
      },
    })
  }

  return (
    <ConfirmDialog
      open
      title={text.title(posicion.codigo, posicion.puesto.nombre)}
      description={text.description}
      confirmLabel={text.confirm}
      cancelLabel={text.cancel}
      busy={suprimir.isPending}
      error={error}
      onConfirm={confirmar}
      onCancel={onCerrar}
    />
  )
}
