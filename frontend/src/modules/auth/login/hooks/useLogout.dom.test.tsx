// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook, screen, waitFor } from '@testing-library/react'
import type { ReactNode } from 'react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { logout } from '../api'
import { currentUserKey } from './sessionKeys'
import { useLogout } from './useLogout'

vi.mock('../api', () => ({ login: vi.fn(), logout: vi.fn(), fetchCurrentUser: vi.fn() }))

function Ubicacion() {
  return <span data-testid="ruta">{useLocation().pathname}</span>
}

function montar() {
  const client = new QueryClient()
  client.setQueryData(currentUserKey, { id: 1, name: 'Admin', email: 'a@hrms.test', role: 'admin' })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/']}>
        {children}
        <Routes>
          <Route path="*" element={<Ubicacion />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  )
  return { client, ...renderHook(() => useLogout(), { wrapper }) }
}

beforeEach(() => {
  localStorage.setItem('hrms.token', 'tok')
  vi.mocked(logout).mockReset()
})

describe('useLogout', () => {
  it('revoca la sesión, limpia token y caché y vuelve a /login', async () => {
    vi.mocked(logout).mockResolvedValue()
    const { result, client } = montar()

    act(() => result.current.mutate())

    await waitFor(() => expect(localStorage.getItem('hrms.token')).toBeNull())
    expect(logout).toHaveBeenCalledOnce()
    expect(client.getQueryData(currentUserKey)).toBeUndefined()
    await waitFor(() => expect(screen.getByTestId('ruta')).toHaveTextContent('/login'))
  })

  it('limpia la sesión local aunque el servidor falle y no reintenta', async () => {
    vi.mocked(logout).mockRejectedValue(httpError(500))
    const { result, client } = montar()

    act(() => result.current.mutate())

    await waitFor(() => expect(localStorage.getItem('hrms.token')).toBeNull())
    expect(logout).toHaveBeenCalledOnce()
    expect(client.getQueryData(currentUserKey)).toBeUndefined()
    await waitFor(() => expect(screen.getByTestId('ruta')).toHaveTextContent('/login'))
  })
})
