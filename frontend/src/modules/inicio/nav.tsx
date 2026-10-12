import type { ShellNavItem } from '../../shared/layouts/AppShell'
import { IconHome } from '../../shared/ui/icons'
import { inicioText } from './text'

export const inicioNav: ShellNavItem = { to: '/', label: inicioText.title, icon: <IconHome />, end: true }
