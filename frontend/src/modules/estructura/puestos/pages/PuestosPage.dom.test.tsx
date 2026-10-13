// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { detalle, pagina, puesto } from '../../../../test/puestosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { listarPuestos, obtenerPuesto } from '../api'
import { PuestosPage } from './PuestosPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarPuestos: vi.fn(),
  obtenerPuesto: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function comoRol(role: 'admin' | 'user') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role } } as ReturnType<typeof useCurrentUser>)
}

function renderPage(entrada = '/puestos') {
  return renderRoutes(<Route path="/puestos" element={<PuestosPage />} />, entrada)
}

const items = [
  puesto({ id: 1, codigo: 'PU-001', nombre: 'Analista de datos' }),
  puesto({ id: 2, codigo: 'PU-002', nombre: 'Jefe de talento humano', descripcion: 'Lidera la gestión del talento.' }),
]

beforeEach(() => {
  vi.mocked(listarPuestos).mockReset().mockResolvedValue(pagina(items))
  vi.mocked(obtenerPuesto).mockReset().mockResolvedValue(detalle())
  comoRol('user')
})

describe('PuestosPage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(listarPuestos).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando puestos…' })).toBeInTheDocument()
  })

  it('lista código, nombre, descripción y estado sin mostrar el detalle', async () => {
    renderPage()

    const lista = await screen.findByRole('list', { name: 'Listado de puestos' })
    const filas = within(lista)
    expect(filas.getByText('PU-001')).toBeInTheDocument()
    expect(filas.getByText('Analista de datos')).toBeInTheDocument()
    expect(filas.getByText('Lidera la gestión del talento.')).toBeInTheDocument()
    expect(filas.getAllByText('Activo')).toHaveLength(2)
    expect(screen.queryByText('Misión')).not.toBeInTheDocument()
    expect(screen.getByText('Mostrando 1–2 de 2')).toBeInTheDocument()
    expect(obtenerPuesto).not.toHaveBeenCalled()
  })

  it('muestra el estado Inactivo como etiqueta', async () => {
    vi.mocked(listarPuestos).mockResolvedValue(pagina([puesto({ id: 3, nombre: 'Retirado', estado: 'Inactivo' })]))
    comoRol('admin')
    renderPage('/puestos?estado=Inactivo')

    expect(await screen.findByText('Retirado')).toBeInTheDocument()
    expect(within(screen.getByRole('list')).getByText('Inactivo')).toBeInTheDocument()
  })

  it('al Usuario no le ofrece crear ni el filtro de estado, y no envía estado', async () => {
    renderPage()
    await screen.findByText('Analista de datos')

    expect(screen.queryByRole('link', { name: 'Nuevo puesto' })).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Filtrar por estado')).not.toBeInTheDocument()
    expect(vi.mocked(listarPuestos).mock.calls[0][0].estado).toBeUndefined()
  })

  it('al Administrador le ofrece crear y el filtro de estado con Activos por defecto', async () => {
    comoRol('admin')
    renderPage()
    await screen.findByText('Analista de datos')

    expect(screen.getByRole('link', { name: 'Nuevo puesto' })).toHaveAttribute('href', '/puestos/nuevo')
    expect(screen.getByLabelText('Filtrar por estado')).toHaveValue('Activo')
    expect(vi.mocked(listarPuestos).mock.calls[0][0].estado).toBe('Activo')
  })

  it('no existe ningún botón de eliminar', async () => {
    comoRol('admin')
    renderPage()
    await screen.findByText('Analista de datos')

    expect(screen.queryByRole('button', { name: /eliminar/i })).not.toBeInTheDocument()
  })

  it('guarda búsqueda y estado en la URL y no escribe Activo', async () => {
    comoRol('admin')
    renderPage()
    const user = userEvent.setup()
    await screen.findByText('Analista de datos')

    await user.selectOptions(screen.getByLabelText('Filtrar por estado'), 'Todos')
    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('/puestos?estado=Todos'))
    await user.selectOptions(screen.getByLabelText('Filtrar por estado'), 'Activo')
    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent(/^\/puestos$/))

    await user.type(screen.getByLabelText('Buscar puesto'), 'gestion')
    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('q=gestion'))
  })

  it('con una búsqueda sin coincidencias muestra el mensaje y permite limpiarla', async () => {
    vi.mocked(listarPuestos).mockResolvedValue(pagina([]))
    renderPage('/puestos?q=zzz')

    expect(await screen.findByText('Ningún puesto coincide con la búsqueda.')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Limpiar filtros' }))
    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent(/^\/puestos$/))
  })

  it('al Usuario sin puestos le muestra el vacío sencillo', async () => {
    vi.mocked(listarPuestos).mockResolvedValue(pagina([]))
    renderPage()

    expect(await screen.findByText('Aún no hay puestos')).toBeInTheDocument()
    expect(screen.queryByText(/ver también los inactivos/)).not.toBeInTheDocument()
  })

  it('al Administrador con todos los puestos inactivos y el filtro por defecto le sugiere ver los inactivos', async () => {
    vi.mocked(listarPuestos).mockResolvedValue(pagina([]))
    comoRol('admin')
    renderPage()

    expect(await screen.findByText('No hay puestos activos')).toBeInTheDocument()
    expect(screen.getByText('Cambia el filtro para ver también los inactivos.')).toBeInTheDocument()
    // Uno en el encabezado y otro como acción del estado vacío.
    expect(screen.getAllByRole('link', { name: 'Nuevo puesto' })).toHaveLength(2)
  })

  it('muestra el error con Reintentar', async () => {
    vi.mocked(listarPuestos).mockRejectedValueOnce(httpError(500)).mockResolvedValue(pagina(items))
    renderPage()

    expect(await screen.findByText('No se pudieron cargar los puestos')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Analista de datos')).toBeInTheDocument()
  })
})
