// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { competencia, detalle, pagina } from '../../../../test/competenciasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { listarCompetencias, obtenerCompetencia } from '../api'
import { CompetenciasPage } from './CompetenciasPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarCompetencias: vi.fn(),
  obtenerCompetencia: vi.fn(),
  cambiarEstadoCompetencia: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function comoRol(role: 'admin' | 'user') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role } } as ReturnType<typeof useCurrentUser>)
}

function renderPage(entrada = '/competencias') {
  return renderRoutes(<Route path="/competencias" element={<CompetenciasPage />} />, entrada)
}

const items = [
  competencia({ id: 1, nombre: 'Liderazgo', tipo: 'Cardinal' }),
  competencia({ id: 2, codigo: 'COMP-0002', nombre: 'Gestión de proyectos', tipo: 'Especifica', descripcion: 'Planificar proyectos.' }),
]

beforeEach(() => {
  vi.mocked(listarCompetencias).mockReset().mockResolvedValue(pagina(items))
  vi.mocked(obtenerCompetencia).mockReset().mockResolvedValue(detalle())
  comoRol('user')
})

describe('CompetenciasPage (consulta)', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(listarCompetencias).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando competencias…' })).toBeInTheDocument()
  })

  it('lista código, nombre, descripción, tipo y estado sin mostrar grados', async () => {
    renderPage()

    expect(await screen.findByText('Liderazgo')).toBeInTheDocument()
    const tabla = within(screen.getByRole('table', { name: 'Listado de competencias' }))
    expect(tabla.getByText('COMP-0001')).toBeInTheDocument()
    expect(tabla.getByText('Planificar proyectos.')).toBeInTheDocument()
    expect(tabla.getByText('Cardinal')).toBeInTheDocument()
    expect(tabla.getByText('Específica')).toBeInTheDocument()
    expect(tabla.getAllByText('Activo')).toHaveLength(2)
    expect(screen.queryByText('Grado A')).not.toBeInTheDocument()
    expect(screen.getByText('Mostrando 1–2 de 2')).toBeInTheDocument()
  })

  it('muestra el estado Inactivo como etiqueta', async () => {
    vi.mocked(listarCompetencias).mockResolvedValue(pagina([competencia({ id: 3, nombre: 'Retirada', estado: 'Inactivo' })]))
    comoRol('admin')
    renderPage('/competencias?estado=Inactivo')

    expect(await screen.findByText('Retirada')).toBeInTheDocument()
    expect(within(screen.getByRole('table')).getByText('Inactivo')).toBeInTheDocument()
  })

  it('al Usuario no le ofrece crear, editar, activar ni desactivar ni el filtro de estado', async () => {
    renderPage()
    await screen.findByText('Liderazgo')

    expect(screen.queryByRole('link', { name: 'Nueva competencia' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /^Editar/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^(Activar|Desactivar)/ })).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Filtrar por estado')).not.toBeInTheDocument()
  })

  it('al Usuario no le envía el estado ni siquiera si la URL lo trae', async () => {
    renderPage('/competencias?estado=Todos')
    await screen.findByText('Liderazgo')

    expect(listarCompetencias).toHaveBeenCalledWith({ q: '', tipo: undefined, estado: undefined, page: 1 })
  })

  it('al Administrador le ofrece crear, editar, desactivar y el filtro de estado', async () => {
    comoRol('admin')
    renderPage()
    await screen.findByText('Liderazgo')

    expect(screen.getByRole('link', { name: 'Nueva competencia' })).toHaveAttribute('href', '/competencias/nueva')
    expect(screen.getByRole('link', { name: 'Editar Liderazgo' })).toHaveAttribute('href', '/competencias/1/editar')
    expect(screen.getByRole('button', { name: 'Desactivar Gestión de proyectos' })).toHaveTextContent('Desactivar')
    expect(screen.getByLabelText('Filtrar por estado')).toHaveValue('Activo')
  })

  it('el botón dice Activar en una competencia inactiva', async () => {
    vi.mocked(listarCompetencias).mockResolvedValue(pagina([competencia({ id: 3, nombre: 'Retirada', estado: 'Inactivo' })]))
    comoRol('admin')
    renderPage('/competencias?estado=Inactivo')

    expect(await screen.findByRole('button', { name: 'Activar Retirada' })).toHaveTextContent('Activar')
  })

  it('el Administrador envía el estado y lo guarda en la URL (Activo no se escribe)', async () => {
    comoRol('admin')
    renderPage()
    await screen.findByText('Liderazgo')
    expect(listarCompetencias).toHaveBeenLastCalledWith({ q: '', tipo: undefined, estado: 'Activo', page: 1 })

    await userEvent.setup().selectOptions(screen.getByLabelText('Filtrar por estado'), 'Todos')

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('estado=Todos'))
    await waitFor(() => expect(listarCompetencias).toHaveBeenLastCalledWith({ q: '', tipo: undefined, estado: 'Todos', page: 1 }))

    await userEvent.setup().selectOptions(screen.getByLabelText('Filtrar por estado'), 'Activo')
    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent(/^\/competencias$/))
  })

  it('muestra el estado vacío sin filtros', async () => {
    vi.mocked(listarCompetencias).mockResolvedValue(pagina([]))
    renderPage()

    expect(await screen.findByRole('heading', { name: 'Aún no hay competencias' })).toBeInTheDocument()
  })

  it('al Administrador sin competencias activas le indica que puede ver las inactivas', async () => {
    vi.mocked(listarCompetencias).mockResolvedValue(pagina([]))
    comoRol('admin')
    renderPage()

    expect(await screen.findByRole('heading', { name: 'No hay competencias activas' })).toBeInTheDocument()
    expect(screen.getByText(/Cambia el filtro de estado/)).toBeInTheDocument()
  })

  it('muestra "No hay resultados" con filtros y permite limpiarlos', async () => {
    vi.mocked(listarCompetencias).mockResolvedValue(pagina([]))
    renderPage('/competencias?q=zzz&tipo=Cardinal')

    expect(await screen.findByRole('heading', { name: 'No hay resultados' })).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Limpiar filtros' }))

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent(/^\/competencias$/))
    expect(screen.getByLabelText('Buscar competencia por nombre')).toHaveValue('')
  })

  it('muestra el error con "Reintentar" y vuelve a cargar', async () => {
    vi.mocked(listarCompetencias).mockRejectedValueOnce(httpError(500)).mockResolvedValueOnce(pagina(items))
    renderPage()

    expect(await screen.findByText('No se pudo cargar las competencias')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Liderazgo')).toBeInTheDocument()
  })

  it('lee búsqueda, filtro y página de la URL', async () => {
    renderPage('/competencias?q=lid&tipo=Cardinal&page=2')
    await screen.findByText('Liderazgo')

    expect(listarCompetencias).toHaveBeenCalledWith({ q: 'lid', tipo: 'Cardinal', estado: undefined, page: 2 })
    expect(screen.getByLabelText('Buscar competencia por nombre')).toHaveValue('lid')
    expect(screen.getByLabelText('Filtrar por tipo')).toHaveValue('Cardinal')
  })

  it('aplica la búsqueda a la URL tras una breve pausa y vuelve a la página 1', async () => {
    renderPage('/competencias?page=3')
    await screen.findByText('Liderazgo')

    await userEvent.setup().type(screen.getByLabelText('Buscar competencia por nombre'), 'trab')

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('/competencias?q=trab'))
    await waitFor(() => expect(listarCompetencias).toHaveBeenLastCalledWith({ q: 'trab', tipo: undefined, estado: undefined, page: 1 }))
  })

  it('aplica el filtro por tipo a la URL', async () => {
    renderPage()
    await screen.findByText('Liderazgo')

    await userEvent.setup().selectOptions(screen.getByLabelText('Filtrar por tipo'), 'Especifica')

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('tipo=Especifica'))
  })

  it('pagina y conserva la página en la URL', async () => {
    vi.mocked(listarCompetencias).mockResolvedValue(pagina(items, { total: 12, last_page: 2, to: 10 }))
    renderPage()
    await screen.findByText('Liderazgo')

    await userEvent.setup().click(screen.getByRole('button', { name: 'Siguiente' }))

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('page=2'))
  })

  it('despliega y oculta los cuatro grados con "Visualizar grados"', async () => {
    renderPage()
    await screen.findByText('Liderazgo')
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: /Visualizar grados.*Liderazgo/ }))

    expect(await screen.findByText('Descripción del grado A.')).toBeInTheDocument()
    expect(screen.getByText('Descripción del grado D.')).toBeInTheDocument()
    expect(obtenerCompetencia).toHaveBeenCalledWith(1)

    await user.click(screen.getByRole('button', { name: /Ocultar grados.*Liderazgo/ }))
    expect(screen.queryByText('Descripción del grado A.')).not.toBeInTheDocument()
  })
})
