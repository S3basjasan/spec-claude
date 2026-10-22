import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconStairs } from '../../../shared/ui/icons'
import { rutasText } from './text'

/** «Rutas de carrera»: el Administrador las gestiona y el Colaborador consulta la suya; el backend es la autoridad. */
export const rutasNav: ShellNavItem = {
  to: '/rutas',
  label: rutasText.navLabel,
  icon: <IconStairs />,
}
