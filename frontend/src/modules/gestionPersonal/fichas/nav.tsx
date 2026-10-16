import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconFileText, IconList } from '../../../shared/ui/icons'
import { fichasText } from './text'

/** «Colaboradores»: solo el Administrador; el backend sigue siendo la autoridad. */
export const colaboradoresNav: ShellNavItem = {
  to: '/colaboradores',
  label: fichasText.navLabel,
  icon: <IconList />,
  roles: ['admin'],
}

/** «Mi ficha»: la consulta propia del Colaborador. */
export const miFichaNav: ShellNavItem = {
  to: '/mi-ficha',
  label: fichasText.miFichaNavLabel,
  icon: <IconFileText />,
  roles: ['user'],
}
