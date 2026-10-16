// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { categoria, paginaDeCategorias } from '../../../../test/categoriasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { cambiarEstadoCategoria, crearCategoria, editarCategoria, listarCategorias } from '../api'
import { CategoriasPage } from './CategoriasPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarCategorias: vi.fn(),
  crearCategoria: vi.fn(),
  editarCategoria: vi.fn(),
  cambiarEstadoCategoria: vi.fn(),
}))

function renderPagina(entrada = '/categorias-documentales') {
  return renderRoutes(<Route path="/categorias-documentales" element={<CategoriasPage />} />, entrada)
}

const contratos = categoria({ id: 1, nombre: 'Contratos' })
const antigua = categoria({ id: 2, nombre: 'Antigua', estado: 'Inactiva', version: 3 })

beforeEach(() => {
  vi.mocked(listarCategorias).mockReset().mockResolvedValue(paginaDeCategorias([contratos]))
  vi.mocked(crearCategoria).mockReset().mockResolvedValue(categoria({ id: 9, nombre: 'Nueva' }))
  vi.mocked(editarCategoria).mockReset().mockResolvedValue(categoria({ version: 2 }))
  vi.mocked(cambiarEstadoCategoria).mockReset().mockResolvedValue(categoria({ estado: 'Inactiva', version: 2 }))
})

describe('CategoriasPage · listado', () => {
  it('muestra el estado de carga', () => {
    vi.mocked(listarCategorias).mockReturnValue(new Promise(() => {}))
    renderPagina()

    expect(screen.getByRole('status', { name: 'Cargando categorías…' })).toBeInTheDocument()
  })

  it('lista las activas por defecto con nombre y estado, sin botón Eliminar', async () => {
    renderPagina()

    expect(await screen.findByText('Contratos')).toBeInTheDocument()
    expect(screen.getByText('Activa')).toBeInTheDocument()
    expect(vi.mocked(listarCategorias).mock.calls[0][0]).toMatchObject({ estado: 'Activa' })
    expect(screen.queryByRole('button', { name: /eliminar/i })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Editar Contratos' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Desactivar Contratos' })).toBeInTheDocument()
  })

  it('muestra el estado vacío con la acción Nueva categoría', async () => {
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategorias([]))
    renderPagina()

    expect(await screen.findByText('Aún no hay categorías documentales')).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'Nueva categoría' }).length).toBeGreaterThanOrEqual(1)
  })

  it('con filtros sin coincidencias muestra el mensaje y permite limpiar', async () => {
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategorias([]))
    renderPagina('/categorias-documentales?q=zzz')

    expect(await screen.findByText('Ninguna categoría coincide con la búsqueda.')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Limpiar filtros' }))
    await waitFor(() => expect(vi.mocked(listarCategorias).mock.calls.at(-1)?.[0]).toMatchObject({ q: '', estado: 'Activa' }))
  })

  it('un error muestra Reintentar y vuelve a consultar', async () => {
    vi.mocked(listarCategorias).mockRejectedValueOnce(httpError(403))
    renderPagina()

    expect(await screen.findByText('No se pudieron cargar las categorías')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByText('Contratos')).toBeInTheDocument()
  })

  it('el filtro de estado y la búsqueda se aplican a la consulta y a la URL', async () => {
    const user = userEvent.setup()
    renderPagina()
    await screen.findByText('Contratos')

    await user.selectOptions(screen.getByLabelText('Estado'), 'Inactiva')
    await waitFor(() => expect(vi.mocked(listarCategorias).mock.calls.at(-1)?.[0]).toMatchObject({ estado: 'Inactiva' }))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('estado=Inactiva')

    await user.type(screen.getByLabelText('Buscar categoría'), 'cert')
    await waitFor(() => expect(vi.mocked(listarCategorias).mock.calls.at(-1)?.[0]).toMatchObject({ q: 'cert', estado: 'Inactiva' }))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('q=cert')
  })

  it('pagina conservando el filtro', async () => {
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategorias([contratos], { total: 30, last_page: 2, to: 20 }))
    renderPagina()
    await screen.findByText('Contratos')

    await userEvent.setup().click(screen.getByRole('button', { name: 'Siguiente' }))

    await waitFor(() => expect(vi.mocked(listarCategorias).mock.calls.at(-1)?.[0]).toMatchObject({ page: 2 }))
  })
})

describe('CategoriasPage · alta y edición', () => {
  it('crea una categoría válida, cierra el diálogo y avisa', async () => {
    const user = userEvent.setup()
    renderPagina()
    await user.click((await screen.findAllByRole('button', { name: 'Nueva categoría' }))[0])
    await user.type(screen.getByLabelText('Nombre'), '  Nueva  ')
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    await waitFor(() => expect(crearCategoria).toHaveBeenCalledWith('Nueva'))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(screen.getByText('Categoría creada.')).toBeInTheDocument()
  })

  it('muestra el error de nombre obligatorio y de 101 caracteres junto al campo sin enviar', async () => {
    const user = userEvent.setup()
    renderPagina()
    await user.click((await screen.findAllByRole('button', { name: 'Nueva categoría' }))[0])
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Este campo es obligatorio.')).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('Revisa los campos marcados')

    await user.type(screen.getByLabelText('Nombre'), 'a'.repeat(101))
    await user.click(screen.getByRole('button', { name: 'Guardar' }))
    expect(await screen.findByText('No puede superar los 100 caracteres.')).toBeInTheDocument()
    expect(crearCategoria).not.toHaveBeenCalled()
  })

  it('muestra el error de nombre duplicado del servidor y conserva lo escrito', async () => {
    vi.mocked(crearCategoria).mockRejectedValueOnce(
      httpError(422, { message: 'x', code: 'VALIDACION', errors: { nombre: ['Ya existe una categoría con ese nombre.'] } }),
    )
    const user = userEvent.setup()
    renderPagina()
    await user.click((await screen.findAllByRole('button', { name: 'Nueva categoría' }))[0])
    await user.type(screen.getByLabelText('Nombre'), 'contratos')
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Ya existe una categoría con ese nombre.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('contratos')
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('un doble clic en Guardar envía una sola petición', async () => {
    let resolver: (valor: ReturnType<typeof categoria>) => void = () => {}
    vi.mocked(crearCategoria).mockReturnValue(new Promise((r) => (resolver = r)))
    const user = userEvent.setup()
    renderPagina()
    await user.click((await screen.findAllByRole('button', { name: 'Nueva categoría' }))[0])
    await user.type(screen.getByLabelText('Nombre'), 'Nueva')
    await user.dblClick(screen.getByRole('button', { name: 'Guardar' }))

    await waitFor(() => expect(crearCategoria).toHaveBeenCalledTimes(1))
    resolver(categoria())
  })

  it('edita el nombre enviando la versión de la fila', async () => {
    const user = userEvent.setup()
    renderPagina()
    await user.click(await screen.findByRole('button', { name: 'Editar Contratos' }))
    const dialogo = screen.getByRole('dialog')
    expect(within(dialogo).getByText('Activa')).toBeInTheDocument()
    const campo = within(dialogo).getByLabelText('Nombre')
    await user.clear(campo)
    await user.type(campo, 'Contratos laborales')
    await user.click(within(dialogo).getByRole('button', { name: 'Guardar' }))

    await waitFor(() => expect(editarCategoria).toHaveBeenCalledWith(1, 'Contratos laborales', 1))
    expect(await screen.findByText('Categoría actualizada.')).toBeInTheDocument()
  })

  it('un 409 conserva lo escrito y tras «Actualizar datos» se envía la versión nueva', async () => {
    vi.mocked(editarCategoria).mockRejectedValueOnce(httpError(409, { message: 'cambió', code: 'CATEGORIA_MODIFICADA' }))
    const user = userEvent.setup()
    renderPagina()
    await user.click(await screen.findByRole('button', { name: 'Editar Contratos' }))
    const campo = screen.getByLabelText('Nombre')
    await user.clear(campo)
    await user.type(campo, 'Mi cambio')
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('La información cambió')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('Mi cambio')

    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategorias([categoria({ id: 1, nombre: 'Del otro', version: 4 })]))
    await user.click(screen.getByRole('button', { name: 'Actualizar datos' }))
    await waitFor(() => expect(screen.queryByText('La información cambió')).not.toBeInTheDocument())
    expect(screen.getByLabelText('Nombre')).toHaveValue('Mi cambio')

    await user.click(screen.getByRole('button', { name: 'Guardar' }))
    await waitFor(() => expect(editarCategoria).toHaveBeenCalledTimes(2))
    expect(vi.mocked(editarCategoria).mock.calls[1]).toEqual([1, 'Mi cambio', 4])
  })

  it('Escape cierra el formulario y devuelve el foco al botón que lo abrió', async () => {
    const user = userEvent.setup()
    renderPagina()
    const boton = await screen.findByRole('button', { name: 'Editar Contratos' })
    await user.click(boton)
    await user.keyboard('{Escape}')

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(boton).toHaveFocus()
  })
})

describe('CategoriasPage · desactivar y reactivar', () => {
  it('desactivar pide confirmación con el aviso de conservación y cambia el estado', async () => {
    const user = userEvent.setup()
    renderPagina()
    await user.click(await screen.findByRole('button', { name: 'Desactivar Contratos' }))

    const dialogo = screen.getByRole('alertdialog')
    expect(within(dialogo).getByText(/Sus documentos se conservarán/)).toBeInTheDocument()
    await user.click(within(dialogo).getByRole('button', { name: 'Desactivar categoría' }))

    await waitFor(() => expect(cambiarEstadoCategoria).toHaveBeenCalledWith(1, 'Inactiva', 1))
    expect(await screen.findByText('Categoría desactivada. Sus documentos se conservan.')).toBeInTheDocument()
  })

  it('cancelar la confirmación no cambia nada', async () => {
    const user = userEvent.setup()
    renderPagina()
    await user.click(await screen.findByRole('button', { name: 'Desactivar Contratos' }))
    await user.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(cambiarEstadoCategoria).not.toHaveBeenCalled()
  })

  it('reactivar una inactiva confirma y envía su versión', async () => {
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategorias([antigua]))
    vi.mocked(cambiarEstadoCategoria).mockResolvedValue({ ...antigua, estado: 'Activa', version: 4 })
    const user = userEvent.setup()
    renderPagina('/categorias-documentales?estado=Inactiva')
    await user.click(await screen.findByRole('button', { name: 'Reactivar Antigua' }))

    const dialogo = screen.getByRole('alertdialog')
    expect(within(dialogo).getByText(/No se reactivan documentos archivados/)).toBeInTheDocument()
    await user.click(within(dialogo).getByRole('button', { name: 'Reactivar categoría' }))

    await waitFor(() => expect(cambiarEstadoCategoria).toHaveBeenCalledWith(2, 'Activa', 3))
    expect(await screen.findByText(/Categoría reactivada/)).toBeInTheDocument()
  })

  it('un 409 mantiene el diálogo, avisa del cambio y vuelve a consultar', async () => {
    vi.mocked(cambiarEstadoCategoria).mockRejectedValueOnce(httpError(409, { code: 'CATEGORIA_MODIFICADA' }))
    const user = userEvent.setup()
    renderPagina()
    await user.click(await screen.findByRole('button', { name: 'Desactivar Contratos' }))
    const llamadasAntes = vi.mocked(listarCategorias).mock.calls.length
    await user.click(screen.getByRole('button', { name: 'Desactivar categoría' }))

    expect(await screen.findByText(/La información de la categoría cambió/)).toBeInTheDocument()
    await waitFor(() => expect(vi.mocked(listarCategorias).mock.calls.length).toBeGreaterThan(llamadasAntes))
    expect(screen.getByRole('alertdialog')).toBeInTheDocument()
  })

  it('un fallo conserva el estado anterior y no muestra éxito', async () => {
    vi.mocked(cambiarEstadoCategoria).mockRejectedValueOnce(httpError(500))
    const user = userEvent.setup()
    renderPagina()
    await user.click(await screen.findByRole('button', { name: 'Desactivar Contratos' }))
    await user.click(screen.getByRole('button', { name: 'Desactivar categoría' }))

    expect(await screen.findByText(/conserva su estado anterior/)).toBeInTheDocument()
    expect(screen.queryByText(/Categoría desactivada/)).not.toBeInTheDocument()
  })
})
