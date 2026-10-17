import { Alert } from '../../../../shared/ui/Alert'
import { microText } from '../text'

/** Nota de alcance (spec 4.3): los micro-contenidos refuerzan conocimientos, no desarrollan competencias conductuales. */
export function AvisoConocimientos({ detalle = false }: { detalle?: boolean }) {
  return (
    <Alert tone="neutral">
      <span className="font-semibold">{microText.notaConocimientos}</span>
      {detalle && <> {microText.notaConocimientosDetalle}</>}
    </Alert>
  )
}
