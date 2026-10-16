import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconFileText } from '../../../shared/ui/icons'
import { planText } from './text'

/** «Plan anual»: el Administrador gestiona los planes y el Colaborador consulta los suyos; el backend es la autoridad. */
export const planFormacionNav: ShellNavItem = {
  to: '/plan-formacion',
  label: planText.navLabel,
  icon: <IconFileText />,
}
