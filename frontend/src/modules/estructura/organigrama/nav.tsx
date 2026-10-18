import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconSitemap } from '../../../shared/ui/icons'
import { organigramaText } from './text'

export const organigramaNav: ShellNavItem = {
  to: '/organigrama',
  label: organigramaText.navLabel,
  icon: <IconSitemap />,
}
