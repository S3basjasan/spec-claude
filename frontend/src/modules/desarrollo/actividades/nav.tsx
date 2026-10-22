import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconSchool } from '../../../shared/ui/icons'
import { actividadesText } from './text'

/** «Actividades»: el Administrador gestiona la ejecución del plan y el Colaborador consulta las suyas; el backend es la autoridad. */
export const actividadesNav: ShellNavItem = {
  to: '/actividades',
  label: actividadesText.navLabel,
  icon: <IconSchool />,
}
