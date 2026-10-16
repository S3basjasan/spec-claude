import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconDownload } from '../../../shared/ui/icons'
import { exportacionText } from './text'

/** «Exportar mi ficha»: acción propia del Colaborador; el servidor decide si hay ficha y permiso de exportación. */
export const exportarMiFichaNav: ShellNavItem = {
  to: '/exportar-mi-ficha',
  label: exportacionText.navLabel,
  icon: <IconDownload />,
  roles: ['user'],
}
