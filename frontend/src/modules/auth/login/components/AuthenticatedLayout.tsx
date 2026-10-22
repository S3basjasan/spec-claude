import { AppShell, type ShellNavGroup } from '../../../../shared/layouts/AppShell'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { useLogout } from '../hooks/useLogout'

interface AuthenticatedLayoutProps {
  /** Navegación lateral compuesta por la aplicación a partir de la de cada módulo. */
  navGroups: ShellNavGroup[]
}

/** Conecta el App Shell (presentacional) con la sesión: identidad y cierre de sesión. */
export function AuthenticatedLayout({ navGroups }: AuthenticatedLayoutProps) {
  const { data: user } = useCurrentUser()
  const logout = useLogout()

  if (!user) return null

  return (
    <AppShell
      user={{ name: user.name, role: user.role }}
      navGroups={navGroups}
      onLogout={() => logout.mutate()}
      isLoggingOut={logout.isPending}
    />
  )
}
