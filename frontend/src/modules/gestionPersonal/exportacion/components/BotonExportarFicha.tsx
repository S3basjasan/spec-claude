import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { IconDownload } from '../../../../shared/ui/icons'
import { mensajeDeExportacion, useExportarFicha } from '../hooks/useExportarFicha'
import { exportacionText } from '../text'

interface Props {
  /** Ficha a exportar; `null` = la propia. */
  colaboradorId: number | null
  codigo?: string | null
  /** Se llama cuando el servidor responde 403: la capacidad ya no está autorizada y la acción debe ocultarse. */
  onSinPermiso?: () => void
}

/** Exportar ficha: progreso, sin doble clic, descarga solo de un PDF válido y reintento ante fallos. */
export function BotonExportarFicha({ colaboradorId, codigo = null, onSinPermiso }: Props) {
  const exportar = useExportarFicha(colaboradorId, codigo)
  const [error, setError] = useState<{ mensaje: string; permiso: boolean } | null>(null)
  const [aviso, setAviso] = useState<string | null>(null)

  function solicitar() {
    if (exportar.isPending) return
    setError(null)
    setAviso(null)
    exportar.mutate(undefined, {
      onSuccess: (nombre) => setAviso(exportacionText.success(nombre)),
      onError: (fallo) => {
        const detalle = mensajeDeExportacion(fallo)
        setError(detalle)
        if (detalle.permiso) onSinPermiso?.()
      },
    })
  }

  return (
    <div className="flex flex-col items-start gap-3">
      <Button variant="secondary" onClick={solicitar} loading={exportar.isPending}>
        <IconDownload size={18} />
        {exportar.isPending ? exportacionText.buttonBusy : exportacionText.button}
      </Button>
      {exportar.isPending && (
        <p role="status" className="text-sm text-muted">
          {exportacionText.progress}
        </p>
      )}
      {aviso !== null && (
        <p role="status" className="rounded-lg bg-soft px-3.5 py-3 text-sm font-semibold text-accent">
          {aviso}
        </p>
      )}
      {error !== null && (
        <div className="flex flex-col items-start gap-2">
          <Alert>{error.mensaje}</Alert>
          {!error.permiso && (
            <Button variant="secondary" onClick={solicitar}>
              {exportacionText.retry}
            </Button>
          )}
        </div>
      )}
    </div>
  )
}
