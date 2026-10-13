import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconFileText } from '../../../shared/ui/icons'
import { puestosText } from './text'

export const puestosNav: ShellNavItem = {
  to: '/puestos',
  label: puestosText.navLabel,
  icon: <IconFileText />,
}
