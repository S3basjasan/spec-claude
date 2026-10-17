import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconAward } from '../../../shared/ui/icons'
import { categoriasReconocimientoText } from './text'

/** «Categorías de reconocimiento»: todos consultan; las acciones de gestión son del Administrador y el backend es la autoridad. */
export const categoriasReconocimientoNav: ShellNavItem = {
  to: '/compensaciones/categorias',
  label: categoriasReconocimientoText.navLabel,
  icon: <IconAward />,
}
