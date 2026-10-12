import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconList } from '../../../shared/ui/icons'
import { comportamientosText } from './text'

export const comportamientosNav: ShellNavItem = {
  to: '/comportamientos',
  label: comportamientosText.navLabel,
  icon: <IconList />,
}
