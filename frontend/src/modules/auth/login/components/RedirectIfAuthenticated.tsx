import { Navigate, Outlet } from 'react-router-dom'
import { tokenStorage } from '../../../../shared/api/tokenStorage'

/** Con sesión iniciada, las rutas públicas (como /login) llevan a la pantalla principal. */
export function RedirectIfAuthenticated() {
  if (tokenStorage.get() !== null) {
    return <Navigate to="/" replace />
  }
  return <Outlet />
}
