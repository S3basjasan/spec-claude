// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { renderHook, waitFor } from '@testing-library/react'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { categoria, paginaDeCategorias } from '../../../../test/categoriasFixtures'
import { categoriasKey, useCategoriasActivas } from '../index'
import { listarCategorias } from '../api'
import { useGuardarCategoria } from './useGuardarCategoria'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarCategorias: vi.fn(),
  crearCategoria: vi.fn(),
}))

function envoltorio() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  const Envoltorio = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
  return { client, Envoltorio }
}

beforeEach(() => {
  vi.mocked(listarCategorias).mockReset().mockResolvedValue(paginaDeCategorias([categoria({ nombre: 'Contratos' })]))
})

describe('useCategoriasActivas', () => {
  it('consulta solo las activas con el tamaño de página máximo', async () => {
    const { Envoltorio } = envoltorio()
    const { result } = renderHook(() => useCategoriasActivas(), { wrapper: Envoltorio })

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data?.map((c) => c.nombre)).toEqual(['Contratos'])
    expect(vi.mocked(listarCategorias).mock.calls[0][0]).toEqual({ estado: 'Activa', per_page: 100 })
  })

  it('se refresca al invalidar la familia tras una mutación del catálogo', async () => {
    const { client, Envoltorio } = envoltorio()
    const { result } = renderHook(
      () => {
        // Las propiedades se leen durante el render para que React Query las siga y vuelva a renderizar.
        const activas = useCategoriasActivas()
        useGuardarCategoria()
        return { exito: activas.isSuccess, datos: activas.data }
      },
      { wrapper: Envoltorio },
    )
    await waitFor(() => expect(result.current.exito).toBe(true))
    const llamadas = vi.mocked(listarCategorias).mock.calls.length

    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategorias([categoria({ nombre: 'Contratos' }), categoria({ id: 2, nombre: 'Nueva' })]))
    await client.invalidateQueries({ queryKey: categoriasKey })

    await waitFor(() => expect(result.current.datos).toHaveLength(2))
    expect(vi.mocked(listarCategorias).mock.calls.length).toBeGreaterThan(llamadas)
  })
})
