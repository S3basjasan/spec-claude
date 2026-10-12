import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { tokenStorage } from '../../../../shared/api/tokenStorage'
import { logout } from '../api'

/**
 * Cierra la sesión. Tanto si el servidor responde como si falla, la sesión local se limpia
 * para que la persona nunca quede atrapada con una sesión que ya no quiere.
 */
export function useLogout() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  return useMutation({
    mutationFn: logout,
    retry: false,
    onSettled: () => {
      tokenStorage.clear()
      queryClient.clear()
      navigate('/login', { replace: true })
    },
  })
}
