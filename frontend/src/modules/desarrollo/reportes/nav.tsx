import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconChartBar } from '../../../shared/ui/icons'
import { reportesText } from './text'

/** «Reportes»: el Administrador evalúa los resultados de la formación y el Colaborador ve su progreso; el backend es la autoridad. */
export const reportesNav: ShellNavItem = {
  to: '/reportes',
  label: reportesText.navLabel,
  icon: <IconChartBar />,
}
