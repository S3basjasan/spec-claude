import type { ReactNode } from 'react'
import { useCurrentUser } from '../../../auth/login'

/**
 * Guarda de presentación: renderiza sus hijos solo al Administrador (botón y formulario de gestión). El backend sigue
 * siendo la autoridad.
 */
export function RequireAdmin({ children }: { children: ReactNode }) {
  const { data: usuario } = useCurrentUser()

  return usuario?.role === 'admin' ? <>{children}</> : null
}
