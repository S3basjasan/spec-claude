import { IconBook, IconFileText, IconHelp, IconVideo } from '../../../../shared/ui/icons'
import type { TipoMicro } from '../api'

/** Ícono del tipo de micro-contenido (decorativo: el nombre del tipo siempre va en texto). */
export function IconoTipo({ tipo, size = 20 }: { tipo: TipoMicro; size?: number }) {
  switch (tipo) {
    case 'Video':
      return <IconVideo size={size} />
    case 'PDF':
      return <IconFileText size={size} />
    case 'Articulo':
      return <IconBook size={size} />
    case 'Quiz':
      return <IconHelp size={size} />
  }
}
