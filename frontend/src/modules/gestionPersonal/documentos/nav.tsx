import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconFileText } from '../../../shared/ui/icons'
import { documentosText } from './text'

/** «Mis documentos»: la consulta propia del Colaborador; el servidor decide si hay ficha y permiso documental. */
export const misDocumentosNav: ShellNavItem = {
  to: '/mis-documentos',
  label: documentosText.navLabel,
  icon: <IconFileText />,
  roles: ['user'],
}
