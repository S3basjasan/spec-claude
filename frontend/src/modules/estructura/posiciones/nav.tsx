import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconUsers } from '../../../shared/ui/icons'
import { posicionesText } from './text'

export const posicionesNav: ShellNavItem = {
  to: '/posiciones',
  label: posicionesText.navLabel,
  icon: <IconUsers />,
}
