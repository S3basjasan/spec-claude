import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconHelp } from '../../../shared/ui/icons'
import { preguntasText } from './text'

export const preguntasNav: ShellNavItem = {
  to: '/preguntas',
  label: preguntasText.navLabel,
  icon: <IconHelp />,
}
