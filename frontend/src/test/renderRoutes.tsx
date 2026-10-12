import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import type { ReactNode } from 'react'
import { MemoryRouter, Routes } from 'react-router-dom'
import { Ubicacion } from './Ubicacion'

/** Renderiza rutas con React Query y un enrutador en memoria; expone la ubicación actual en `ubicacion`. */
export function renderRoutes(rutas: ReactNode, entrada: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  const utils = render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[entrada]}>
        <Routes>{rutas}</Routes>
        <Ubicacion />
      </MemoryRouter>
    </QueryClientProvider>,
  )
  return { client, ...utils }
}
