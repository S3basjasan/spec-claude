import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { tokenStorage } from '../../../../shared/api/tokenStorage'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { loginText } from '../text'

/** Guarda de presentación: sin sesión redirige a /login. El backend mantiene la autoridad. */
export function RequireAuth() {
  const location = useLocation()
  const { isPending, isError, refetch } = useCurrentUser()

  if (tokenStorage.get() === null) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }

  if (isPending) {
    return (
      <div role="status" aria-label={loginText.sessionLoadingLabel} className="mx-auto flex max-w-xl flex-col gap-4 p-8">
        <Skeleton className="h-8 w-1/2" />
        <Skeleton className="h-24" />
      </div>
    )
  }

  if (isError) {
    return (
      <ErrorState
        title={loginText.sessionErrorTitle}
        description={loginText.sessionErrorDescription}
        retryLabel={loginText.retry}
        onRetry={() => void refetch()}
      />
    )
  }

  return <Outlet />
}
