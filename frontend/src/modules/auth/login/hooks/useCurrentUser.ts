import { useQuery } from '@tanstack/react-query'
import { tokenStorage } from '../../../../shared/api/tokenStorage'
import { fetchCurrentUser } from '../api'
import { currentUserKey } from './sessionKeys'

/** Persona autenticada según el backend (`GET /api/auth/me`); solo se consulta si hay token. */
export function useCurrentUser() {
  return useQuery({
    queryKey: currentUserKey,
    queryFn: fetchCurrentUser,
    enabled: tokenStorage.get() !== null,
    staleTime: Infinity,
  })
}
