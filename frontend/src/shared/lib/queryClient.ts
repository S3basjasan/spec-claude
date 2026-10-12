import { QueryClient } from '@tanstack/react-query'
import { toApiError } from '../api/httpClient'

export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: (failureCount, error) => toApiError(error).status !== 401 && failureCount < 1,
        refetchOnWindowFocus: false,
      },
      // Las escrituras nunca se reintentan automáticamente.
      mutations: { retry: false },
    },
  })
}

export const queryClient = createQueryClient()
