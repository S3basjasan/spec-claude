import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconChartBar } from '../../../shared/ui/icons'
import { adecuacionText } from './text'

/** «Adecuación»: el Administrador genera y analiza los diagnósticos y el Colaborador consulta el suyo; el backend es la autoridad. */
export const adecuacionNav: ShellNavItem = {
  to: '/adecuacion',
  label: adecuacionText.navLabel,
  icon: <IconChartBar />,
}
