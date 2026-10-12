// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { detalle, pagina, registro } from '../../../../test/preguntasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { listarPreguntas, obtenerPreguntas } from '../api'
import { PreguntasPage } from './PreguntasPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarPreguntas: vi.fn(),
  obtenerPreguntas: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function comoRol(role: 'admin' | 'user') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role } } as ReturnType<typeof useCurrentUser>)
}

function renderPage(entrada = '/preguntas') {
  return renderRoutes(<Route path="/preguntas" element={<PreguntasPage />} />, entrada)
}

const items = [
  registro({ competencia_id: 1, nombre: 'Liderazgo', tipo: 'Cardinal' }),
  registro({ competencia_id: 2, codigo: 'COMP-0002', nombre: 'Gestión de proyectos', tipo: 'Especifica', descripcion: 'Planificar proyectos.' }),
]

beforeEach(() => {
  vi.mocked(listarPreguntas).mockReset().mockResolvedValue(pagina(items))
  vi.mocked(obtenerPreguntas).mockReset().mockResolvedValue(detalle())
  comoRol('user')
})

describe('PreguntasPage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(listarPreguntas).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando preguntas…' })).toBeInTheDocument()
  })

  it('lista código, nombre, descripción, tipo y estado sin mostrar preguntas', async () => {
    renderPage()

    expect(await screen.findByText('Liderazgo')).toBeInTheDocument()
    const tabla = within(screen.getByRole('table', { name: 'Listado de preguntas' }))
    expect(tabla.getByText('COMP-0001')).toBeInTheDocument()
    expect(tabla.getByText('Planificar proyectos.')).toBeInTheDocument()
    expect(tabla.getByText('Cardinal')).toBeInTheDocument()
    expect(tabla.getByText('Específica')).toBeInTheDocument()
    expect(tabla.getAllByText('Activo')).toHaveLength(2)
    expect(screen.queryByText('Grado A')).not.toBeInTheDocument()
    expect(screen.getByText('Mostrando 1–2 de 2')).toBeInTheDocument()
  })

  it('muestra el estado Inactivo como etiqueta', async () => {
    vi.mocked(listarPreguntas).mockResolvedValue(pagina([registro({ competencia_id: 3, nombre: 'Retirada', estado: 'Inactivo' })]))
    comoRol('admin')
    renderPage('/preguntas?estado=Inactivo')

    expect(await screen.findByText('Retirada')).toBeInTheDocument()
    expect(within(screen.getByRole('table')).getByText('Inactivo')).toBeInTheDocument()
  })

  it('al Usuario no le ofrece crear, editar ni el filtro de estado, y no envía estado', async () => {
    renderPage()
    await screen.findByText('Liderazgo')

    expect(screen.queryByRole('link', { name: 'Nuevo registro' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /^Editar/ })).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Filtrar por estado')).not.toBeInTheDocument()
    expect(vi.mocked(listarPreguntas).mock.calls[0][0].estado).toBeUndefined()
  })

  it('al Administrador le ofrece crear, editar y el filtro de estado', async () => {
    comoRol('admin')
    renderPage()
    await screen.findByText('Liderazgo')

    expect(screen.getByRole('link', { name: 'Nuevo registro' })).toHaveAttribute('href', '/preguntas/nuevo')
    expect(screen.getByRole('link', { name: 'Editar Liderazgo' })).toHaveAttribute('href', '/preguntas/1/editar')
    expect(screen.getByLabelText('Filtrar por estado')).toHaveValue('Activo')
    expect(vi.mocked(listarPreguntas).mock.calls[0][0].estado).toBe('Activo')
  })

  it('no existe ningún botón de activar, desactivar ni eliminar', async () => {
    comoRol('admin')
    renderPage()
    await screen.findByText('Liderazgo')

    expect(screen.queryByRole('button', { name: /activar|desactivar|eliminar/i })).not.toBeInTheDocument()
  })

  it('guarda búsqueda, tipo y estado en la URL y no escribe Activo', async () => {
    comoRol('admin')
    renderPage()
    const user = userEvent.setup()
    await screen.findByText('Liderazgo')

    await user.selectOptions(screen.getByLabelText('Filtrar por tipo'), 'Especifica')
    await user.selectOptions(screen.getByLabelText('Filtrar por estado'), 'Todos')
    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('/preguntas?tipo=Especifica&estado=Todos'))
    await user.selectOptions(screen.getByLabelText('Filtrar por estado'), 'Activo')

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('/preguntas?tipo=Especifica'))
    expect(screen.getByTestId('ubicacion')).not.toHaveTextContent('estado')

    await user.type(screen.getByLabelText('Buscar por nombre de la competencia'), 'lider')
    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('q=lider'))
  })

  it('muestra el vacío sin filtros y "sin resultados" con filtros', async () => {
    vi.mocked(listarPreguntas).mockResolvedValue(pagina([]))
    renderPage()
    expect(await screen.findByText('Aún no hay preguntas')).toBeInTheDocument()
  })

  it('con filtros sin coincidencias ofrece limpiarlos', async () => {
    vi.mocked(listarPreguntas).mockResolvedValue(pagina([]))
    renderPage('/preguntas?q=zzz')

    expect(await screen.findByText('No hay resultados')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Limpiar filtros' }))
    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent(/^\/preguntas$/))
  })

  it('muestra el error con Reintentar', async () => {
    vi.mocked(listarPreguntas).mockRejectedValueOnce(httpError(500)).mockResolvedValue(pagina(items))
    renderPage()

    expect(await screen.findByText('No se pudieron cargar las preguntas')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Liderazgo')).toBeInTheDocument()
  })
})
