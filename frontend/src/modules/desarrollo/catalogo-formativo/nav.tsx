import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconSchool } from '../../../shared/ui/icons'
import { catalogoText } from './text'

export const catalogoFormativoNav: ShellNavItem = {
  to: '/formacion',
  label: catalogoText.navLabel,
  icon: <IconSchool />,
}
