import { Link } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { fichasText } from '../text'

/** Aviso de identificación duplicada con enlace para abrir la ficha existente (FR-013). */
export function AlertaDuplicada({ fichaExistenteId }: { fichaExistenteId: number | null }) {
  return (
    <Alert>
      <p>{fichasText.form.duplicate}</p>
      {fichaExistenteId !== null && (
        <Link to={`/colaboradores/${fichaExistenteId}`} className="mt-1 inline-block font-semibold underline">
          {fichasText.form.openExisting}
        </Link>
      )}
    </Alert>
  )
}
