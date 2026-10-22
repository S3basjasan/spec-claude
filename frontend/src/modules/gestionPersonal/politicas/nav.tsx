import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconFileText } from '../../../shared/ui/icons'
import { politicasText } from './text'

/** «Políticas de personal»: solo el Administrador; el backend sigue siendo la autoridad. */
export const politicasNav: ShellNavItem = {
  to: '/politicas-personal',
  label: politicasText.navLabel,
  icon: <IconFileText />,
  roles: ['admin'],
}
