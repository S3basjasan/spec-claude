import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import type { FuenteNoDisponible } from '../api'
import { rutasText } from '../text'

interface AvisoDeFuentesProps {
  fuentes: FuenteNoDisponible[] | undefined
  onReintentar: () => void
}

/** Aviso neutro cuando otra fuente (Estructura o Personal) no respondió: se muestran los datos propios, sin inventar nada. */
export function AvisoDeFuentes({ fuentes, onReintentar }: AvisoDeFuentesProps) {
  if (!fuentes || fuentes.length === 0) return null

  return (
    <Alert tone="neutral">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <span>{fuentes.map((f) => rutasText.fuentes[f]).join(' ')}</span>
        <Button variant="secondary" onClick={onReintentar}>
          {rutasText.fuentes.reintentar}
        </Button>
      </div>
    </Alert>
  )
}
