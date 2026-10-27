import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconClock, IconPower } from '../../../shared/ui/icons'
import { offboardingText } from './text'

/** «Offboarding»: solo el Administrador; el backend sigue siendo la autoridad. */
export const offboardingNav: ShellNavItem = {
  to: '/offboarding',
  label: offboardingText.navLabel,
  icon: <IconPower />,
  roles: ['admin'],
}

/** «Mi desvinculación»: el Colaborador consulta su salida y responde la entrevista. */
export const miDesvinculacionNav: ShellNavItem = {
  to: '/mi-desvinculacion',
  label: offboardingText.miNavLabel,
  icon: <IconClock />,
  roles: ['user'],
}
