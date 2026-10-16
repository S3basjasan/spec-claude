// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { detalle } from '../../../../test/fichasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { actualizarColaborador, crearColaborador, obtenerColaborador } from '../api'
import { RequireAdmin } from '../components/RequireAdmin'
import { FichaFormPage } from './FichaFormPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  crearColaborador: vi.fn(),
  actualizarColaborador: vi.fn(),
  obtenerColaborador: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function comoRol(role: 'admin' | 'user') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role } } as ReturnType<typeof useCurrentUser>)
}

function renderForm(entrada = '/colaboradores/nuevo') {
  return renderRoutes(
    <>
      <Route element={<RequireAdmin />}>
        <Route path="/colaboradores/nuevo" element={<FichaFormPage />} />
        <Route path="/colaboradores/:colaboradorId/editar" element={<FichaFormPage />} />
      </Route>
      <Route path="/colaboradores" element={<p>Listado de colaboradores</p>} />
      <Route path="/colaboradores/:colaboradorId" element={<p>Detalle de ficha</p>} />
    </>,
    entrada,
  )
}

beforeEach(() => {
  vi.mocked(crearColaborador).mockReset().mockResolvedValue(detalle({ id: 7, codigo: 'EMP-007' }))
  vi.mocked(actualizarColaborador).mockReset().mockResolvedValue(detalle({ version: 2 }))
  vi.mocked(obtenerColaborador).mockReset().mockResolvedValue(detalle({ nacionalidad: 'Ecuatoriana' }))
  comoRol('admin')
})

async function completarMinimo(user: ReturnType<typeof userEvent.setup>) {
  await user.type(await screen.findByLabelText('Número de identificación'), '0102030405')
  await user.type(screen.getByLabelText('Nombres'), 'Ana')
  await user.type(screen.getByLabelText('Apellidos'), 'Núñez')
}

describe('FichaFormPage (crear)', () => {
  it('muestra los cuatro grupos y que el código se asigna al guardar', async () => {
    renderForm()

    expect(await screen.findByRole('group', { name: 'Identificación' })).toBeInTheDocument()
    expect(screen.getByRole('group', { name: 'Datos personales' })).toBeInTheDocument()
    expect(screen.getByRole('group', { name: 'Contacto' })).toBeInTheDocument()
    expect(screen.getByRole('group', { name: 'Contacto de emergencia' })).toBeInTheDocument()
    expect(screen.getByText('Se asigna al guardar')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /eliminar/i })).not.toBeInTheDocument()
  })

  it('el Usuario no accede al formulario', async () => {
    comoRol('user')
    renderForm()

    expect(await screen.findByText('No tienes permiso para ver esta información')).toBeInTheDocument()
  })

  it('muestra errores por campo y no envía nada si faltan datos obligatorios', async () => {
    const user = userEvent.setup()
    renderForm()

    await user.click(await screen.findByRole('button', { name: 'Guardar' }))

    expect(await screen.findAllByText('Este campo es obligatorio.')).toHaveLength(3)
    expect(crearColaborador).not.toHaveBeenCalled()
  })

  it('envía la ficha con una clave de operación, y abre el detalle', async () => {
    const user = userEvent.setup()
    renderForm()
    await completarMinimo(user)

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    await waitFor(() => expect(crearColaborador).toHaveBeenCalledTimes(1))
    const [datos, clave] = vi.mocked(crearColaborador).mock.calls[0]
    expect(datos).toMatchObject({ tipo_identificacion: 'Cedula', numero_identificacion: '0102030405', nombres: 'Ana', apellidos: 'Núñez', numero_hijos: null })
    expect(clave).toMatch(/^[0-9a-f-]{36}$/)
    expect(await screen.findByText('Detalle de ficha')).toBeInTheDocument()
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('/colaboradores/7')
  })

  it('bloquea el envío mientras guarda', async () => {
    const user = userEvent.setup()
    vi.mocked(crearColaborador).mockReturnValue(new Promise(() => {}))
    renderForm()
    await completarMinimo(user)

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    const boton = await screen.findByRole('button', { name: 'Guardando…' })
    expect(boton).toBeDisabled()
    expect(crearColaborador).toHaveBeenCalledTimes(1)
  })

  it('ante un duplicado ofrece abrir la ficha existente y conserva lo escrito', async () => {
    const user = userEvent.setup()
    vi.mocked(crearColaborador).mockRejectedValue(
      httpError(422, {
        message: 'Ya existe',
        code: 'IDENTIFICACION_DUPLICADA',
        errors: { numero_identificacion: ['Ya existe una ficha con ese tipo y número de identificación.'] },
        ficha_existente: { id: 12, codigo: 'EMP-001' },
      }),
    )
    renderForm()
    await completarMinimo(user)

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    const enlace = await screen.findByRole('link', { name: 'Abrir ficha existente' })
    expect(enlace).toHaveAttribute('href', '/colaboradores/12')
    expect(screen.getByLabelText('Número de identificación')).toHaveValue('0102030405')
  })

  it('conserva los valores y muestra el fallo cuando no hay conexión, sin mensaje de éxito', async () => {
    const user = userEvent.setup()
    vi.mocked(crearColaborador).mockRejectedValue(networkError())
    renderForm()
    await completarMinimo(user)

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText(/No se pudo conectar con el servidor/)).toBeInTheDocument()
    expect(screen.getByLabelText('Nombres')).toHaveValue('Ana')
    expect(screen.queryByText('Ficha creada.')).not.toBeInTheDocument()
  })

  it('Cancelar vuelve al listado sin crear nada', async () => {
    const user = userEvent.setup()
    renderForm()

    await user.click(await screen.findByRole('button', { name: 'Cancelar' }))

    expect(await screen.findByText('Listado de colaboradores')).toBeInTheDocument()
    expect(crearColaborador).not.toHaveBeenCalled()
  })
})

describe('FichaFormPage (editar)', () => {
  it('muestra el código en solo lectura y los valores actuales', async () => {
    renderForm('/colaboradores/1/editar')

    expect(await screen.findByDisplayValue('Ecuatoriana')).toBeInTheDocument()
    expect(screen.getByText('EMP-001')).toBeInTheDocument()
    expect(screen.queryByText('Se asigna al guardar')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Nombres')).toHaveValue('Ana')
  })

  it('envía solo los campos cambiados con la versión abierta', async () => {
    const user = userEvent.setup()
    renderForm('/colaboradores/1/editar')
    const nombres = await screen.findByLabelText('Nombres')

    await user.clear(nombres)
    await user.type(nombres, 'Ana María')
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    await waitFor(() => expect(actualizarColaborador).toHaveBeenCalledWith(1, { nombres: 'Ana María' }, 1))
  })

  it('ante un conflicto de versión avisa, ofrece actualizar y conserva lo escrito sin reenviar solo', async () => {
    const user = userEvent.setup()
    vi.mocked(actualizarColaborador).mockRejectedValue(httpError(409, { message: 'cambió', code: 'FICHA_MODIFICADA' }))
    renderForm('/colaboradores/1/editar')
    const nombres = await screen.findByLabelText('Nombres')
    await user.clear(nombres)
    await user.type(nombres, 'Cambio pendiente')

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('La ficha cambió')).toBeInTheDocument()
    vi.mocked(obtenerColaborador).mockResolvedValue(detalle({ version: 2, nacionalidad: 'Ecuatoriana' }))
    await user.click(screen.getByRole('button', { name: 'Actualizar datos' }))

    await waitFor(() => expect(obtenerColaborador).toHaveBeenCalledTimes(2))
    expect(screen.getByLabelText('Nombres')).toHaveValue('Cambio pendiente')
    expect(actualizarColaborador).toHaveBeenCalledTimes(1)
  })
})
