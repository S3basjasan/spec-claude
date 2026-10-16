import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconFolder } from '../../../shared/ui/icons'
import { categoriasText } from './text'

/** «Categorías documentales»: solo el Administrador; el backend sigue siendo la autoridad. */
export const categoriasNav: ShellNavItem = {
  to: '/categorias-documentales',
  label: categoriasText.navLabel,
  icon: <IconFolder />,
  roles: ['admin'],
}
