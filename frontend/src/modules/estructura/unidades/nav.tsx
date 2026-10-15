import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconSitemap } from '../../../shared/ui/icons'
import { unidadesText } from './text'

export const unidadesNav: ShellNavItem = {
  to: '/unidades',
  label: unidadesText.navLabel,
  icon: <IconSitemap />,
}
