import { useMutation, useQueryClient } from '@tanstack/react-query'
import { tokenStorage } from '../../../../shared/api/tokenStorage'
import { login } from '../api'
import { currentUserKey } from './sessionKeys'

export function useLogin() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: login,
    retry: false,
    onSuccess: ({ token, user }) => {
      tokenStorage.set(token)
      queryClient.setQueryData(currentUserKey, user)
    },
  })
}
