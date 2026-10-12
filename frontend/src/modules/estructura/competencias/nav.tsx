import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconAward } from '../../../shared/ui/icons'
import { competenciasText } from './text'

export const competenciasNav: ShellNavItem = {
  to: '/competencias',
  label: competenciasText.navLabel,
  icon: <IconAward />,
}
