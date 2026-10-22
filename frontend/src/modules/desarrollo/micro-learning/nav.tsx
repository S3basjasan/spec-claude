import type { ShellNavItem } from '../../../shared/layouts/AppShell'
import { IconPlayerPlay } from '../../../shared/ui/icons'
import { microText } from './text'

export const microLearningNav: ShellNavItem = {
  to: '/micro-learning',
  label: microText.navLabel,
  icon: <IconPlayerPlay />,
}
