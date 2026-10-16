import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconChartBar } from '../../../shared/ui/icons'
import { dnfText } from './text'

/** «DNF»: el Administrador genera diagnósticos y el Colaborador consulta el propio; el backend es la autoridad. */
export const dnfNav: ShellNavItem = {
  to: '/dnf',
  label: dnfText.navLabel,
  icon: <IconChartBar />,
}
