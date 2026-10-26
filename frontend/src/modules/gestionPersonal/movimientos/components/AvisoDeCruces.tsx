import { Alert } from '../../../../shared/ui/Alert'
import { Link } from 'react-router-dom'
import type { CruceDePeriodo } from '../api'
import { movimientosText } from '../text'

const text = movimientosText.detail

/** Señal (no bloqueo): el período se cruza con otras solicitudes; el Administrador debe reconocerlo para aprobar. */
export function AvisoDeCruces({ cruces }: { cruces: CruceDePeriodo[] }) {
  if (cruces.length === 0) return null

  return (
    <Alert tone="neutral">
      <p className="font-semibold">{text.crossings}</p>
      <p>{text.crossingsNotice}</p>
      <ul className="mt-2 list-disc pl-5">
        {cruces.map((cruce) => (
          <li key={cruce.id}>
            <Link to={`/movimientos-personal/${cruce.id}`} className="font-semibold text-accent underline">
              {text.crossingRow(movimientosText.tipos[cruce.tipo], movimientosText.estados[cruce.estado], cruce.inicio, cruce.fin)}
            </Link>
          </li>
        ))}
      </ul>
    </Alert>
  )
}
