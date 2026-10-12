// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { login } from '../api'
import { LoginPage } from './LoginPage'

vi.mock('../api', () => ({ login: vi.fn(), logout: vi.fn(), fetchCurrentUser: vi.fn() }))

const usuario = { id: 1, name: 'Admin', email: 'admin@hrms.test', role: 'admin' as const }

function renderPage() {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/login']}>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/" element={<p>Pantalla principal</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

async function completar(email = 'admin@hrms.test', password = 'password-de-prueba') {
  const user = userEvent.setup()
  await user.type(screen.getByLabelText('Correo electrónico'), email)
  await user.type(screen.getByLabelText('Contraseña'), password)
  await user.click(screen.getByRole('button', { name: 'Ingresar' }))
}

beforeEach(() => {
  localStorage.clear()
  vi.mocked(login).mockReset()
})

describe('LoginPage', () => {
  it('muestra errores por campo y no llama al servidor con el formulario vacío', async () => {
    renderPage()

    await userEvent.setup().click(screen.getByRole('button', { name: 'Ingresar' }))

    expect(await screen.findByText('Ingresa tu correo electrónico.')).toBeInTheDocument()
    expect(screen.getByText('Ingresa tu contraseña.')).toBeInTheDocument()
    expect(screen.getByLabelText('Correo electrónico')).toHaveAttribute('aria-invalid', 'true')
    expect(login).not.toHaveBeenCalled()
  })

  it('rechaza un correo con formato inválido sin llamar al servidor', async () => {
    renderPage()

    await completar('no-es-correo', 'x')

    expect(await screen.findByText('Ingresa un correo electrónico válido.')).toBeInTheDocument()
    expect(login).not.toHaveBeenCalled()
  })

  it('entra, guarda el token y lleva a la pantalla principal con credenciales válidas', async () => {
    vi.mocked(login).mockResolvedValue({ token: 'tok', user: usuario })
    renderPage()

    await completar('  admin@hrms.test ')

    expect(await screen.findByText('Pantalla principal')).toBeInTheDocument()
    expect(vi.mocked(login).mock.calls[0][0]).toEqual({ email: 'admin@hrms.test', password: 'password-de-prueba' })
    expect(localStorage.getItem('hrms.token')).toBe('tok')
  })

  it('muestra una alerta general con 401 y conserva lo escrito', async () => {
    vi.mocked(login).mockRejectedValue(httpError(401, { message: 'Correo o contraseña incorrectos.' }))
    renderPage()

    await completar()

    expect(await screen.findByRole('alert')).toHaveTextContent('Correo o contraseña incorrectos. Inténtalo de nuevo.')
    expect(screen.getByLabelText('Correo electrónico')).toHaveValue('admin@hrms.test')
    expect(localStorage.getItem('hrms.token')).toBeNull()
  })

  it('mapea los errores 422 del servidor a cada campo', async () => {
    vi.mocked(login).mockRejectedValue(httpError(422, { errors: { password: ['Ingresa tu contraseña.'] } }))
    renderPage()

    await completar('admin@hrms.test', 'x')

    expect(await screen.findByText('Ingresa tu contraseña.')).toBeInTheDocument()
  })

  it('bloquea el botón mientras el envío está pendiente', async () => {
    let resolver: (valor: { token: string; user: typeof usuario }) => void = () => {}
    vi.mocked(login).mockReturnValue(new Promise((resolve) => (resolver = resolve)))
    renderPage()

    await completar()

    const boton = await screen.findByRole('button', { name: 'Ingresando…' })
    expect(boton).toBeDisabled()
    resolver({ token: 'tok', user: usuario })
    await waitFor(() => expect(screen.getByText('Pantalla principal')).toBeInTheDocument())
    expect(login).toHaveBeenCalledOnce()
  })

  it('ante un error de red ofrece reintentar y conserva los valores', async () => {
    vi.mocked(login).mockRejectedValueOnce(networkError()).mockResolvedValueOnce({ token: 'tok', user: usuario })
    renderPage()

    await completar()

    expect(await screen.findByText('No se pudo conectar con el servidor')).toBeInTheDocument()
    expect(screen.getByLabelText('Correo electrónico')).toHaveValue('admin@hrms.test')

    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Pantalla principal')).toBeInTheDocument()
    expect(login).toHaveBeenCalledTimes(2)
  })
})
