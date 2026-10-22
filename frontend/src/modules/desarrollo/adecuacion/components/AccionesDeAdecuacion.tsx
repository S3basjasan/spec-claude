import { Button } from '../../../../shared/ui/Button'
import { adecuacionText } from '../text'

const t = adecuacionText.confirmacion

interface Props {
  /** La adecuación tiene al menos una brecha. */
  conBrechas: boolean
  /** Motivo ya calculado por el servidor, si el detalle lo trae (acciones del detalle). */
  motivo?: string
}

/**
 * «Crear plan de desarrollo» e «Incluir en Programa de Talentos». Siguen deshabilitados mientras no exista el Plan de
 * Desarrollo Individual (o cuando no hay brechas); el motivo siempre se dice en texto, no solo en un `title`.
 */
export function AccionesDeAdecuacion({ conBrechas, motivo }: Props) {
  const texto = motivo ?? (conBrechas ? t.motivoSinPdi : t.motivoSinBrechas)

  return (
    <div className="flex flex-col gap-1.5">
      <div className="flex flex-wrap gap-3">
        <Button variant="primary" type="button" disabled aria-describedby="acciones-motivo">
          {t.crearPlan}
        </Button>
        <Button variant="secondary" type="button" disabled aria-describedby="acciones-motivo">
          {t.incluirPrograma}
        </Button>
      </div>
      <p id="acciones-motivo" className="m-0 text-[13px] text-muted">
        {texto}
      </p>
    </div>
  )
}
