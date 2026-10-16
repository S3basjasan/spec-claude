import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { desarrolloText } from '../text'
import type { ProblemaDeGuardado } from '../validation'

const text = desarrolloText.dialog

interface AvisosDeGuardadoProps {
  problema: ProblemaDeGuardado['aviso']
  /** Con conflicto: vuelve a consultar los datos conservando lo escrito. */
  onActualizar: () => void
}

/** Avisos generales de un diálogo de edición (conflicto, registro inexistente, red…), sobre los campos. */
export function AvisosDeGuardado({ problema, onActualizar }: AvisosDeGuardadoProps) {
  switch (problema) {
    case 'conflicto':
      return (
        <Alert>
          <p className="font-semibold">{text.conflictTitle}</p>
          <p>{text.conflict}</p>
          <Button variant="secondary" className="mt-2" onClick={onActualizar}>
            {text.reload}
          </Button>
        </Alert>
      )
    case 'noExiste':
      return <Alert>{text.notFound}</Alert>
    case 'deshabilitada':
      return <Alert>{text.writeDisabled}</Alert>
    case 'red':
      return <Alert>{text.network}</Alert>
    case 'inesperado':
      return <Alert>{text.unexpected}</Alert>
    default:
      return null
  }
}
