// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { renderHook, waitFor } from '@testing-library/react'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { generarPlan } from '../api'
import { useGenerarPlan } from './useGenerarPlan'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  generarPlan: vi.fn(),
}))

function envoltorio() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
  return { client, wrapper }
}

describe('useGenerarPlan', () => {
  beforeEach(() => {
    vi.mocked(generarPlan).mockReset()
  })

  it('genera el plan e invalida la caché de la DNF', async () => {
    vi.mocked(generarPlan).mockResolvedValue({ plan_formacion_id: 31, generado_por: null, generado_en: '2026-10-15T15:00:00+00:00' })
    const { client, wrapper } = envoltorio()
    const invalidar = vi.spyOn(client, 'invalidateQueries')
    const { result } = renderHook(() => useGenerarPlan(), { wrapper })

    result.current.mutate(7)

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(generarPlan).toHaveBeenCalledWith(7)
    expect(invalidar).toHaveBeenCalledWith({ queryKey: ['dnf'] })
  })

  it('no reintenta automáticamente cuando falla', async () => {
    vi.mocked(generarPlan).mockRejectedValue(httpError(503))
    const { wrapper } = envoltorio()
    const { result } = renderHook(() => useGenerarPlan(), { wrapper })

    result.current.mutate(7)

    await waitFor(() => expect(result.current.isError).toBe(true))
    expect(generarPlan).toHaveBeenCalledTimes(1)
  })
})
