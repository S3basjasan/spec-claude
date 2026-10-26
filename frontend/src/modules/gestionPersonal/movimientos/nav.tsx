import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconClock, IconList } from '../../../shared/ui/icons'
import { movimientosText } from './text'

/** «Movimientos y novedades»: solo el Administrador; el backend sigue siendo la autoridad. */
export const movimientosNav: ShellNavItem = {
  to: '/movimientos-personal',
  label: movimientosText.navLabel,
  icon: <IconList />,
  roles: ['admin'],
}

/** «Mis solicitudes»: el Colaborador consulta y presenta lo propio. */
export const misSolicitudesNav: ShellNavItem = {
  to: '/mis-solicitudes',
  label: movimientosText.misSolicitudesNavLabel,
  icon: <IconClock />,
  roles: ['user'],
}
