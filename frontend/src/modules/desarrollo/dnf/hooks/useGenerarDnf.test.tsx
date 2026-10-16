// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { renderHook, waitFor } from '@testing-library/react'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { generada } from '../../../../test/dnfFixtures'
import { generarDnf } from '../api'
import { useGenerarDnf } from './useGenerarDnf'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  generarDnf: vi.fn(),
}))

function envoltorio() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
  return { client, wrapper }
}

describe('useGenerarDnf', () => {
  beforeEach(() => {
    vi.mocked(generarDnf).mockReset()
  })

  it('genera la DNF e invalida la caché de la DNF', async () => {
    vi.mocked(generarDnf).mockResolvedValue(generada())
    const { client, wrapper } = envoltorio()
    const invalidar = vi.spyOn(client, 'invalidateQueries')
    const { result } = renderHook(() => useGenerarDnf(), { wrapper })

    result.current.mutate({ alcance: 'Individual', colaborador_id: 10 })

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(generarDnf).toHaveBeenCalledWith({ alcance: 'Individual', colaborador_id: 10 })
    expect(invalidar).toHaveBeenCalledWith({ queryKey: ['dnf'] })
  })

  it('no reintenta automáticamente cuando falla (las escrituras no se reintentan)', async () => {
    vi.mocked(generarDnf).mockRejectedValue(httpError(422, { message: 'No hay colaboradores evaluables.' }))
    const { wrapper } = envoltorio()
    const { result } = renderHook(() => useGenerarDnf(), { wrapper })

    result.current.mutate({ alcance: 'Departamento', departamento_id: 5 })

    await waitFor(() => expect(result.current.isError).toBe(true))
    expect(generarDnf).toHaveBeenCalledTimes(1)
  })
})
