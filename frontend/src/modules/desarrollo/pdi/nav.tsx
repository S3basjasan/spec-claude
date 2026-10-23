import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconFileText } from '../../../shared/ui/icons'
import { pdiText } from './text'

/**
 * «PDI» para quien administra y «Mi plan de desarrollo» para la persona colaboradora; ambos llevan a `/pdi`, que decide la
 * vista por permisos. El backend es la autoridad.
 */
export const pdiNav: ShellNavItem = {
  to: '/pdi',
  label: pdiText.navLabel,
  icon: <IconFileText />,
  roles: ['admin'],
}

export const miPlanNav: ShellNavItem = {
  to: '/pdi',
  label: pdiText.navLabelColaborador,
  icon: <IconFileText />,
  roles: ['user'],
}
