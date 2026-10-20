// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { categoriaReconocimiento, opcionDeCompetencia, paginaDeCategoriasReconocimiento } from '../../../../test/categoriasReconocimientoFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { cambiarEstadoCategoria, crearCategoria, editarCategoria, listarCategorias, listarCompetenciasDisponibles, listarCompetenciasFiltro, obtenerCategoria } from '../api'
import { CategoriasReconocimientoPage } from './CategoriasReconocimientoPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarCategorias: vi.fn(),
  obtenerCategoria: vi.fn(),
  crearCategoria: vi.fn(),
  editarCategoria: vi.fn(),
  cambiarEstadoCategoria: vi.fn(),
  listarCompetenciasFiltro: vi.fn(),
  listarCompetenciasDisponibles: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function comoRol(role: 'admin' | 'user') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role } } as ReturnType<typeof useCurrentUser>)
}

function renderPagina(entrada = '/compensaciones/categorias') {
  return renderRoutes(<Route path="/compensaciones/categorias" element={<CategoriasReconocimientoPage />} />, entrada)
}

const equipo = categoriaReconocimiento()
const inactiva = categoriaReconocimiento({ id: 2, codigo: 'CAT-002', nombre: 'Antigua', estado: 'Inactivo' })
const sinCompetencia = categoriaReconocimiento({
  id: 3,
  codigo: 'CAT-003',
  nombre: 'Huérfana',
  competencia: { id: 11, codigo: 'COMP-0011', nombre: 'Retirada', estado: 'Inactivo' },
  competencia_activa: false,
  seleccionable: false,
})

beforeEach(() => {
  comoRol('admin')
  vi.mocked(listarCategorias).mockReset().mockResolvedValue(paginaDeCategoriasReconocimiento([equipo]))
  vi.mocked(obtenerCategoria).mockReset().mockResolvedValue(equipo)
  vi.mocked(crearCategoria).mockReset().mockResolvedValue(categoriaReconocimiento({ id: 9, codigo: 'CAT-009', nombre: 'Nueva' }))
  vi.mocked(editarCategoria).mockReset().mockResolvedValue(categoriaReconocimiento({ nombre: 'Editada' }))
  vi.mocked(cambiarEstadoCategoria).mockReset().mockResolvedValue(categoriaReconocimiento({ estado: 'Inactivo' }))
  vi.mocked(listarCompetenciasFiltro).mockReset().mockResolvedValue([opcionDeCompetencia(), opcionDeCompetencia({ id: 11, nombre: 'Retirada', estado: 'Inactivo' })])
  vi.mocked(listarCompetenciasDisponibles).mockReset().mockResolvedValue([opcionDeCompetencia()])
})

describe('CategoriasReconocimientoPage · listado', () => {
  it('muestra el estado de carga', () => {
    vi.mocked(listarCategorias).mockReturnValue(new Promise(() => {}))
    renderPagina()

    expect(screen.getByRole('status', { name: 'Cargando categorías…' })).toBeInTheDocument()
  })

  it('lista código, ícono, nombre, competencia, puntuación, estado y acciones de gestión para el Administrador', async () => {
    renderPagina()

    expect(await screen.findByText('Trabajo en equipo')).toBeInTheDocument()
    expect(screen.getByText('CAT-001')).toBeInTheDocument()
    expect(screen.getByText('Colaboración', { selector: 'span' })).toBeInTheDocument()
    expect(screen.getByText('100')).toBeInTheDocument()
    expect(screen.getByText('Activo', { selector: 'span' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Ver Trabajo en equipo' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Editar Trabajo en equipo' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Desactivar Trabajo en equipo' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Nueva categoría' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /eliminar/i })).not.toBeInTheDocument()
    expect(vi.mocked(listarCategorias).mock.calls[0][0]).toMatchObject({ estado: 'Activo' })
  })

  it('muestra a un Usuario solo «Ver», sin Nueva categoría ni filtro de estado, y siempre pide las activas', async () => {
    comoRol('user')
    renderPagina('/compensaciones/categorias?estado=Todos')

    expect(await screen.findByText('Trabajo en equipo')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Ver Trabajo en equipo' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Editar|Desactivar|Activar|Nueva categoría/ })).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Estado')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Competencia')).toBeInTheDocument()
    expect(vi.mocked(listarCategorias).mock.calls[0][0]).toMatchObject({ estado: 'Activo' })
  })

  it('el filtro de estado del Administrador envía Todos, Activos o Inactivos y queda en la URL', async () => {
    renderPagina()
    await screen.findByText('Trabajo en equipo')

    await userEvent.setup().selectOptions(screen.getByLabelText('Estado'), 'Todos')

    await waitFor(() => expect(vi.mocked(listarCategorias).mock.calls.at(-1)?.[0]).toMatchObject({ estado: 'Todos' }))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('estado=Todos')
  })

  it('advierte «Competencia inactiva» junto a la competencia', async () => {
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategoriasReconocimiento([sinCompetencia]))
    renderPagina()

    expect(await screen.findByText('Huérfana')).toBeInTheDocument()
    expect(screen.getByText('Competencia inactiva')).toBeInTheDocument()
  })

  it('ofrece al Administrador las competencias inactivas marcadas en el filtro', async () => {
    renderPagina()
    await screen.findByText('Trabajo en equipo')

    const filtro = screen.getByLabelText('Competencia')
    await waitFor(() => expect(within(filtro).getByRole('option', { name: 'Retirada (inactiva)' })).toBeInTheDocument())
    expect(within(filtro).getByRole('option', { name: 'Todas las competencias' })).toBeInTheDocument()
  })

  it('al elegir una competencia la envía al servidor y la guarda en la URL', async () => {
    renderPagina()
    await screen.findByText('Trabajo en equipo')
    await waitFor(() => expect(within(screen.getByLabelText('Competencia')).getAllByRole('option').length).toBeGreaterThan(1))

    await userEvent.setup().selectOptions(screen.getByLabelText('Competencia'), 'Colaboración')

    await waitFor(() => expect(vi.mocked(listarCategorias).mock.calls.at(-1)?.[0]).toMatchObject({ competencia: 10 }))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('competencia=10')
  })

  it('restaura búsqueda, filtros y página desde la URL', async () => {
    renderPagina('/compensaciones/categorias?q=equipo&competencia=10&estado=Inactivo&page=2')

    await screen.findByText('Trabajo en equipo')
    expect(vi.mocked(listarCategorias).mock.calls[0][0]).toEqual({ q: 'equipo', competencia: 10, estado: 'Inactivo', page: 2 })
    expect(screen.getByRole('searchbox')).toHaveValue('equipo')
  })

  it('la búsqueda no admite más de 100 caracteres', async () => {
    renderPagina()
    await screen.findByText('Trabajo en equipo')

    expect(screen.getByRole('searchbox')).toHaveAttribute('maxlength', '100')
  })

  it('muestra el estado vacío del Administrador con la acción Nueva categoría', async () => {
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategoriasReconocimiento([]))
    renderPagina()

    expect(await screen.findByText('Aún no hay categorías de reconocimiento')).toBeInTheDocument()
    expect(screen.getByText('Crea la primera categoría para que pueda usarse en hitos y desafíos.')).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'Nueva categoría' }).length).toBeGreaterThanOrEqual(1)
  })

  it('muestra el estado vacío de un Usuario sin acción de crear', async () => {
    comoRol('user')
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategoriasReconocimiento([]))
    renderPagina()

    expect(await screen.findByText('Cuando Talento Humano cree categorías, aparecerán aquí.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Nueva categoría' })).not.toBeInTheDocument()
  })

  it('con búsqueda sin coincidencias muestra el mensaje de la spec', async () => {
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategoriasReconocimiento([]))
    renderPagina('/compensaciones/categorias?q=zzz')

    expect(await screen.findByText('Sin resultados')).toBeInTheDocument()
    expect(screen.getByText('No se encontraron categorías de reconocimiento que coincidan con la búsqueda.')).toBeInTheDocument()
  })

  it('un error muestra Reintentar y vuelve a consultar', async () => {
    vi.mocked(listarCategorias).mockRejectedValueOnce(httpError(403))
    renderPagina()

    expect(await screen.findByText('No se pudo cargar el listado')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Trabajo en equipo')).toBeInTheDocument()
  })

  it('pagina y guarda la página en la URL', async () => {
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategoriasReconocimiento([equipo], { total: 30, last_page: 2, to: 15 }))
    renderPagina()
    await screen.findByText('Trabajo en equipo')

    await userEvent.setup().click(screen.getByRole('button', { name: /siguiente/i }))

    await waitFor(() => expect(vi.mocked(listarCategorias).mock.calls.at(-1)?.[0]).toMatchObject({ page: 2 }))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('page=2')
  })

  it('recuerda que la puntuación no es dinero', async () => {
    renderPagina()

    expect(await screen.findByText(/No es dinero ni se canjea por bienes, días libres ni bonos/)).toBeInTheDocument()
  })
})

describe('CategoriasReconocimientoPage · detalle', () => {
  it('«Ver» muestra los datos, los indicadores en orden como viñetas y la auditoría al Administrador', async () => {
    renderPagina()
    await userEvent.setup().click(await screen.findByRole('button', { name: 'Ver Trabajo en equipo' }))

    const dialogo = await screen.findByRole('dialog', { name: 'Trabajo en equipo' })
    expect(within(dialogo).getByText('Colabora de forma activa con sus compañeros.')).toBeInTheDocument()
    const items = within(dialogo).getAllByRole('listitem')
    expect(items.map((i) => i.textContent)).toEqual(['Comparte información con su equipo', 'Ayuda a quien lo necesita'])
    expect(within(dialogo).getByText('Auditoría')).toBeInTheDocument()
    expect(within(dialogo).getByText('Usuario 1')).toBeInTheDocument()
    expect(within(dialogo).getByRole('button', { name: 'Editar' })).toBeInTheDocument()
  })

  it('un Usuario no ve la auditoría ni Editar', async () => {
    comoRol('user')
    renderPagina()
    await userEvent.setup().click(await screen.findByRole('button', { name: 'Ver Trabajo en equipo' }))

    const dialogo = await screen.findByRole('dialog', { name: 'Trabajo en equipo' })
    expect(within(dialogo).queryByText('Auditoría')).not.toBeInTheDocument()
    expect(within(dialogo).queryByRole('button', { name: 'Editar' })).not.toBeInTheDocument()
  })

  it('explica que una categoría inactiva se muestra por los registros históricos', async () => {
    comoRol('user')
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategoriasReconocimiento([inactiva]))
    vi.mocked(obtenerCategoria).mockResolvedValue(inactiva)
    renderPagina()
    await userEvent.setup().click(await screen.findByRole('button', { name: 'Ver Antigua' }))

    expect(await screen.findByText('Esta categoría está inactiva. Se muestra porque forma parte de registros históricos.')).toBeInTheDocument()
  })

  it('muestra «No se encontró la categoría» si el servidor responde 404', async () => {
    vi.mocked(obtenerCategoria).mockRejectedValue(httpError(404))
    renderPagina()
    await userEvent.setup().click(await screen.findByRole('button', { name: 'Ver Trabajo en equipo' }))

    expect(await screen.findByText('No se encontró la categoría')).toBeInTheDocument()
    expect(screen.getByText('La categoría que buscas no existe o no está disponible.')).toBeInTheDocument()
  })
})

describe('CategoriasReconocimientoPage · estado', () => {
  it('Desactivar pide confirmación, informa y cambia el estado con la versión leída', async () => {
    renderPagina()
    const usuario = userEvent.setup()
    await usuario.click(await screen.findByRole('button', { name: 'Desactivar Trabajo en equipo' }))

    const dialogo = await screen.findByRole('alertdialog', { name: '¿Desactivar la categoría?' })
    expect(within(dialogo).getByText(/no podrá usarse en nuevos hitos ni desafíos/)).toBeInTheDocument()
    expect(cambiarEstadoCategoria).not.toHaveBeenCalled()

    await usuario.click(within(dialogo).getByRole('button', { name: 'Desactivar' }))

    await waitFor(() => expect(cambiarEstadoCategoria).toHaveBeenCalledWith(1, 'Inactivo', '2026-10-16T14:00:00.123456Z'))
    expect(await screen.findByText('Se desactivó la categoría «Trabajo en equipo».')).toBeInTheDocument()
  })

  it('Cancelar la confirmación no cambia nada', async () => {
    renderPagina()
    const usuario = userEvent.setup()
    await usuario.click(await screen.findByRole('button', { name: 'Desactivar Trabajo en equipo' }))
    await usuario.click(await screen.findByRole('button', { name: 'Cancelar' }))

    expect(cambiarEstadoCategoria).not.toHaveBeenCalled()
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })

  it('Activar no pide confirmación', async () => {
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategoriasReconocimiento([inactiva]))
    vi.mocked(cambiarEstadoCategoria).mockResolvedValue({ ...inactiva, estado: 'Activo' })
    renderPagina()

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Activar Antigua' }))

    await waitFor(() => expect(cambiarEstadoCategoria).toHaveBeenCalledWith(2, 'Activo', inactiva.updated_at))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(await screen.findByText('Se activó la categoría «Antigua».')).toBeInTheDocument()
  })

  it('un 409 al desactivar muestra el aviso en el diálogo y vuelve a consultar el listado', async () => {
    vi.mocked(cambiarEstadoCategoria).mockRejectedValue(httpError(409))
    renderPagina()
    const usuario = userEvent.setup()
    await usuario.click(await screen.findByRole('button', { name: 'Desactivar Trabajo en equipo' }))
    const llamadas = vi.mocked(listarCategorias).mock.calls.length

    await usuario.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Desactivar' }))

    expect(await screen.findByText('La categoría fue modificada por otro usuario. Recarga para ver los cambios.')).toBeInTheDocument()
    await waitFor(() => expect(vi.mocked(listarCategorias).mock.calls.length).toBeGreaterThan(llamadas))
  })

  it('un 409 al activar muestra un aviso de error sobre el listado', async () => {
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategoriasReconocimiento([inactiva]))
    vi.mocked(cambiarEstadoCategoria).mockRejectedValue(httpError(409))
    renderPagina()

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Activar Antigua' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('La categoría fue modificada por otro usuario. Recarga para ver los cambios.')
  })

  it('otro error muestra un mensaje comprensible y no reintenta la mutación', async () => {
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategoriasReconocimiento([inactiva]))
    vi.mocked(cambiarEstadoCategoria).mockRejectedValue(httpError(500))
    renderPagina()

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Activar Antigua' }))

    expect(await screen.findByText('No se pudo cambiar el estado de la categoría. Inténtalo de nuevo.')).toBeInTheDocument()
    expect(cambiarEstadoCategoria).toHaveBeenCalledTimes(1)
  })
})
