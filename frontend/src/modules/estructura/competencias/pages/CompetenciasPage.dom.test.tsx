// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { competencia, detalle, escalas, pagina } from '../../../../test/competenciasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { listarCompetencias, listarEscalas, obtenerCompetencia } from '../api'
import { CompetenciasPage } from './CompetenciasPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarCompetencias: vi.fn(),
  obtenerCompetencia: vi.fn(),
  listarEscalas: vi.fn(),
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
  competencia({ id: 2, codigo: 'COMP-0002', nombre: 'Gestión de proyectos', tipo: 'Especifica' }),
  competencia({ id: 3, codigo: 'COMP-0003', nombre: 'Adaptabilidad', tipo: 'Cardinal', estado: 'Inactivo' }),
]

beforeEach(() => {
  vi.mocked(listarCompetencias).mockReset().mockResolvedValue(pagina(items))
  vi.mocked(obtenerCompetencia).mockReset().mockResolvedValue(detalle())
  vi.mocked(listarEscalas).mockReset().mockResolvedValue(escalas())
  comoRol('user')
})

describe('CompetenciasPage (consulta)', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(listarCompetencias).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando competencias…' })).toBeInTheDocument()
  })

  it('muestra el título, el subtítulo y las tarjetas con código, nombre y tipo, sin descripción ni badge de estado', async () => {
    renderPage()

    expect(await screen.findByRole('heading', { level: 1, name: 'Competencias' })).toBeInTheDocument()
    expect(screen.getByText('Competencias con sus comportamientos y preguntas.')).toBeInTheDocument()
    expect(await screen.findByText('COMP-0001')).toBeInTheDocument()
    expect(screen.getByText('Liderazgo')).toBeInTheDocument()
    const tarjetas = screen.getAllByRole('article')
    expect(within(tarjetas[0]).getByText('Cardinal')).toBeInTheDocument()
    expect(within(tarjetas[1]).getByText('Específica')).toBeInTheDocument()
    expect(screen.queryByText('Guiar e inspirar a otras personas.')).not.toBeInTheDocument()
    expect(screen.queryByText('Activo')).not.toBeInTheDocument()
    expect(screen.queryByText('Inactivo')).not.toBeInTheDocument()
  })

  it('atenúa el nombre de una competencia inactiva', async () => {
    renderPage()

    expect(await screen.findByText('Adaptabilidad')).toHaveClass('text-muted')
    expect(screen.getByText('Liderazgo')).not.toHaveClass('text-muted')
  })

  it('indica cuántas competencias se encontraron', async () => {
    renderPage()
    expect(await screen.findByText('3 competencias')).toBeInTheDocument()

    vi.mocked(listarCompetencias).mockResolvedValue(pagina([items[0]]))
    renderPage('/competencias?q=lider')
    expect(await screen.findByText('1 competencia')).toBeInTheDocument()
  })

  it('no ofrece filtro de estado, ni acciones de gestión al usuario sin rol de administrador', async () => {
    renderPage()
    await screen.findByText('Liderazgo')

    expect(screen.queryByLabelText(/estado/i)).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Nueva competencia' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Desactivar|^Activar/ })).not.toBeInTheDocument()
  })

  it('el administrador ve Nueva competencia y Activar / Desactivar con el nombre de la competencia', async () => {
    comoRol('admin')
    renderPage()
    await screen.findByText('Liderazgo')

    expect(screen.getByRole('button', { name: 'Nueva competencia' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Desactivar Liderazgo' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Activar Adaptabilidad' })).toBeInTheDocument()
  })

  it('pide al servidor la búsqueda, el tipo y la página de la URL, con 12 por página', async () => {
    renderPage('/competencias?q=lider&tipo=Cardinal&page=2')

    await waitFor(() =>
      expect(listarCompetencias).toHaveBeenCalledWith({ q: 'lider', tipo: 'Cardinal', page: 2, per_page: 12 }),
    )
  })

  it('filtra mientras se escribe y guarda la búsqueda en la URL', async () => {
    const usuario = userEvent.setup()
    const { getByTestId } = renderPage()
    await screen.findByText('Liderazgo')

    await usuario.type(screen.getByRole('searchbox', { name: 'Buscar competencia' }), 'lidér')

    await waitFor(() => expect(getByTestId('ubicacion')).toHaveTextContent('/competencias?q=lid%C3%A9r'))
    expect(screen.getByRole('searchbox', { name: 'Buscar competencia' })).toHaveAttribute('placeholder', 'Buscar por nombre')
  })

  it('muestra "Limpiar filtros" solo cuando hay un filtro aplicado y lo quita al pulsarlo', async () => {
    const usuario = userEvent.setup()
    const { getByTestId } = renderPage()
    await screen.findByText('Liderazgo')
    expect(screen.queryByRole('button', { name: 'Limpiar filtros' })).not.toBeInTheDocument()

    await usuario.selectOptions(screen.getByLabelText('Tipo'), 'Especifica')
    await waitFor(() => expect(getByTestId('ubicacion')).toHaveTextContent('tipo=Especifica'))

    await usuario.click(await screen.findByRole('button', { name: 'Limpiar filtros' }))
    await waitFor(() => expect(getByTestId('ubicacion')).toHaveTextContent(/^\/competencias$/))
    expect(screen.queryByRole('button', { name: 'Limpiar filtros' })).not.toBeInTheDocument()
  })

  it('con la búsqueda sin resultados muestra el mensaje y "Limpiar filtros"', async () => {
    vi.mocked(listarCompetencias).mockResolvedValue(pagina([]))
    renderPage('/competencias?q=zzz')

    expect(await screen.findByText(/Ninguna competencia coincide con los filtros\./)).toBeInTheDocument()
    // Uno en la barra de filtros (hay un filtro aplicado) y otro junto al mensaje.
    expect(screen.getAllByRole('button', { name: 'Limpiar filtros' })).toHaveLength(2)
  })

  it('sin competencias y sin filtros muestra el estado vacío', async () => {
    vi.mocked(listarCompetencias).mockResolvedValue(pagina([]))
    renderPage()

    expect(await screen.findByText('Aún no hay competencias')).toBeInTheDocument()
  })

  it('muestra el error con "Reintentar" y vuelve a pedir el listado', async () => {
    const usuario = userEvent.setup()
    vi.mocked(listarCompetencias).mockRejectedValueOnce(new Error('falló'))
    renderPage()

    expect(await screen.findByText('No se pudieron cargar las competencias')).toBeInTheDocument()
    await usuario.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Liderazgo')).toBeInTheDocument()
  })

  it('pagina cuando hay más de una página', async () => {
    const usuario = userEvent.setup()
    vi.mocked(listarCompetencias).mockResolvedValue(pagina(items, { total: 14, last_page: 2, to: 12 }))
    const { getByTestId } = renderPage()

    await screen.findByText('Liderazgo')
    expect(screen.getByText('14 competencias')).toBeInTheDocument()
    await usuario.click(screen.getByRole('button', { name: 'Siguiente' }))

    await waitFor(() => expect(getByTestId('ubicacion')).toHaveTextContent('page=2'))
  })
})

describe('CompetenciasPage (detalle desplegable)', () => {
  it('despliega la tarjeta en la pestaña Competencia con la descripción y los grados', async () => {
    const usuario = userEvent.setup()
    renderPage()

    const abrir = await screen.findByRole('button', { name: 'Ver información adicional de Liderazgo' })
    expect(abrir).toHaveAttribute('aria-expanded', 'false')
    await usuario.click(abrir)

    expect(await screen.findByText('Guiar e inspirar a otras personas.')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Competencia', selected: true })).toBeInTheDocument()
    expect(screen.getByText('Descripción del grado A.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Ocultar información adicional de Liderazgo' })).toHaveAttribute('aria-expanded', 'true')
    expect(obtenerCompetencia).toHaveBeenCalledWith(1)
  })

  it('permite tener varias tarjetas abiertas, cada una con su pestaña', async () => {
    const usuario = userEvent.setup()
    renderPage()

    await usuario.click(await screen.findByRole('button', { name: 'Ver información adicional de Liderazgo' }))
    await usuario.click(await screen.findByRole('button', { name: 'Ver información adicional de Gestión de proyectos' }))
    await waitFor(() => expect(screen.getAllByRole('tablist')).toHaveLength(2))

    const primera = within(screen.getByRole('tablist', { name: 'Información de Liderazgo' }))
    await usuario.click(primera.getByRole('tab', { name: 'Preguntas' }))

    expect(primera.getByRole('tab', { name: 'Preguntas', selected: true })).toBeInTheDocument()
    expect(within(screen.getByRole('tablist', { name: 'Información de Gestión de proyectos' })).getByRole('tab', { name: 'Competencia', selected: true })).toBeInTheDocument()
  })

  it('oculta el detalle al volver a pulsar la tarjeta', async () => {
    const usuario = userEvent.setup()
    renderPage()

    await usuario.click(await screen.findByRole('button', { name: 'Ver información adicional de Liderazgo' }))
    await screen.findByRole('tablist')
    await usuario.click(screen.getByRole('button', { name: 'Ocultar información adicional de Liderazgo' }))

    expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
  })
})
