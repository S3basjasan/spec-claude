// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { networkError } from '../../../../test/apiErrors'
import { fetchCurrentUser } from '../api'
import { RedirectIfAuthenticated } from './RedirectIfAuthenticated'
import { RequireAuth } from './RequireAuth'

vi.mock('../api', () => ({ login: vi.fn(), logout: vi.fn(), fetchCurrentUser: vi.fn() }))

const usuario = { id: 1, name: 'Admin', email: 'admin@hrms.test', role: 'admin' as const }

function renderAt(path: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route element={<RedirectIfAuthenticated />}>
            <Route path="/login" element={<p>Pantalla de login</p>} />
          </Route>
          <Route element={<RequireAuth />}>
            <Route path="/" element={<p>Contenido protegido</p>} />
          </Route>
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  localStorage.clear()
  vi.mocked(fetchCurrentUser).mockReset()
})

describe('RequireAuth', () => {
  it('sin token redirige a /login y no consulta al servidor', () => {
    renderAt('/')

    expect(screen.getByText('Pantalla de login')).toBeInTheDocument()
    expect(screen.queryByText('Contenido protegido')).not.toBeInTheDocument()
    expect(fetchCurrentUser).not.toHaveBeenCalled()
  })

  it('con token muestra un estado de carga y luego el contenido', async () => {
    localStorage.setItem('hrms.token', 'tok')
    vi.mocked(fetchCurrentUser).mockResolvedValue(usuario)

    renderAt('/')

    expect(screen.getByRole('status', { name: 'Verificando tu sesión…' })).toBeInTheDocument()
    expect(await screen.findByText('Contenido protegido')).toBeInTheDocument()
  })

  it('ante un error de red muestra el error con reintento', async () => {
    localStorage.setItem('hrms.token', 'tok')
    vi.mocked(fetchCurrentUser).mockRejectedValueOnce(networkError()).mockResolvedValueOnce(usuario)

    renderAt('/')

    expect(await screen.findByText('No se pudo verificar tu sesión')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByText('Contenido protegido')).toBeInTheDocument()
  })
})

describe('RedirectIfAuthenticated', () => {
  it('sin token deja ver /login', () => {
    renderAt('/login')

    expect(screen.getByText('Pantalla de login')).toBeInTheDocument()
  })

  it('con token redirige de /login a la pantalla principal', async () => {
    localStorage.setItem('hrms.token', 'tok')
    vi.mocked(fetchCurrentUser).mockResolvedValue(usuario)

    renderAt('/login')

    expect(await screen.findByText('Contenido protegido')).toBeInTheDocument()
    expect(screen.queryByText('Pantalla de login')).not.toBeInTheDocument()
  })
})
