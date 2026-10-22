// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { categoriaReconocimiento, opcionDeCompetencia } from '../../../../test/categoriasReconocimientoFixtures'
import { crearCategoria, editarCategoria, listarCompetenciasDisponibles, obtenerCategoria, type Categoria } from '../api'
import { CategoriaFormModal } from './CategoriaFormModal'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  crearCategoria: vi.fn(),
  editarCategoria: vi.fn(),
  obtenerCategoria: vi.fn(),
  listarCompetenciasDisponibles: vi.fn(),
}))

const onCerrar = vi.fn()
const onGuardado = vi.fn()

function renderFormulario(categoria?: Categoria) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <CategoriaFormModal categoria={categoria} onCerrar={onCerrar} onGuardado={onGuardado} />
    </QueryClientProvider>,
  )
}

async function completarValido(usuario: ReturnType<typeof userEvent.setup>) {
  await usuario.type(screen.getByLabelText('Nombre'), 'Orientación al cliente')
  await usuario.type(screen.getByLabelText('Descripción'), 'Resuelve las necesidades de los clientes.')
  await screen.findByRole('option', { name: 'Colaboración' })
  await usuario.selectOptions(screen.getByLabelText('Competencia asociada'), 'Colaboración')
  await usuario.type(screen.getByLabelText('Puntuación base'), '150')
  await usuario.click(screen.getByRole('radio', { name: 'Trofeo' }))
  await usuario.type(screen.getByLabelText('Indicador 1'), 'Atiende con amabilidad')
}

beforeEach(() => {
  onCerrar.mockReset()
  onGuardado.mockReset()
  vi.mocked(crearCategoria).mockReset().mockResolvedValue(categoriaReconocimiento({ id: 9, codigo: 'CAT-009' }))
  vi.mocked(editarCategoria).mockReset().mockResolvedValue(categoriaReconocimiento())
  vi.mocked(obtenerCategoria).mockReset().mockResolvedValue(categoriaReconocimiento())
  vi.mocked(listarCompetenciasDisponibles).mockReset().mockResolvedValue([opcionDeCompetencia(), opcionDeCompetencia({ id: 12, nombre: 'Innovación' })])
})

describe('CategoriaFormModal · crear', () => {
  it('abre con un indicador, explica el código y ofrece solo competencias activas', async () => {
    renderFormulario()

    expect(await screen.findByRole('dialog', { name: 'Nueva categoría' })).toBeInTheDocument()
    expect(screen.getByText('El código (CAT-###) se asigna automáticamente al guardar.')).toBeInTheDocument()
    expect(screen.getAllByRole('textbox', { name: /^Indicador \d$/ })).toHaveLength(1)
    expect(screen.getAllByRole('radio')).toHaveLength(12)
    await waitFor(() => expect(within(screen.getByLabelText('Competencia asociada')).getAllByRole('option')).toHaveLength(3))
    expect(screen.getByRole('button', { name: 'Crear categoría' })).toBeInTheDocument()
  })

  it('envía los datos válidos con los textos recortados', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await completarValido(usuario)

    await usuario.click(screen.getByRole('button', { name: 'Crear categoría' }))

    await waitFor(() => expect(onGuardado).toHaveBeenCalledTimes(1))
    expect(crearCategoria).toHaveBeenCalledWith({
      nombre: 'Orientación al cliente',
      descripcion: 'Resuelve las necesidades de los clientes.',
      competencia_id: 10,
      puntuacion_base: 150,
      icono: 'trofeo',
      indicadores: ['Atiende con amabilidad'],
    })
  })

  it('muestra los errores junto a cada campo sin enviar y conserva lo escrito', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await screen.findByRole('option', { name: 'Colaboración' })
    await usuario.type(screen.getByLabelText('Nombre'), 'ab')

    await usuario.click(screen.getByRole('button', { name: 'Crear categoría' }))

    expect(await screen.findByText('El nombre debe tener entre 3 y 100 caracteres.')).toBeInTheDocument()
    // Descripción, puntuación base y el primer indicador están vacíos: cada uno muestra su propio error.
    expect(screen.getAllByText('Este campo es obligatorio.')).toHaveLength(3)
    expect(screen.getByText('Selecciona una competencia.')).toBeInTheDocument()
    expect(screen.getByText('Selecciona un ícono.')).toBeInTheDocument()
    expect(screen.getByText('Revisa los campos marcados para poder guardar.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('ab')
    expect(crearCategoria).not.toHaveBeenCalled()
  })

  it('deshabilita «Agregar indicador» al llegar a 5 y «Eliminar» cuando queda 1', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await screen.findByRole('dialog')

    expect(screen.getByRole('button', { name: 'Eliminar indicador 1' })).toBeDisabled()
    for (let i = 0; i < 4; i++) await usuario.click(screen.getByRole('button', { name: 'Agregar indicador' }))

    expect(screen.getAllByRole('textbox', { name: /^Indicador \d$/ })).toHaveLength(5)
    expect(screen.getByRole('button', { name: 'Agregar indicador' })).toBeDisabled()
    expect(screen.getByText('5 de 5 indicadores · máximo alcanzado')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Eliminar indicador 3' })).toBeEnabled()

    await usuario.click(screen.getByRole('button', { name: 'Eliminar indicador 3' }))
    expect(screen.getAllByRole('textbox', { name: /^Indicador \d$/ })).toHaveLength(4)
    expect(screen.getByRole('button', { name: 'Agregar indicador' })).toBeEnabled()
  })

  it('marca los indicadores repetidos', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await completarValido(usuario)
    await usuario.click(screen.getByRole('button', { name: 'Agregar indicador' }))
    await usuario.type(screen.getByLabelText('Indicador 2'), '  ATIENDE con amabilidad ')

    await usuario.click(screen.getByRole('button', { name: 'Crear categoría' }))

    expect(await screen.findByText('Los indicadores no pueden repetirse.')).toBeInTheDocument()
    expect(crearCategoria).not.toHaveBeenCalled()
  })

  it('asigna el 422 del servidor al campo, conserva los valores y retira el error al volver a escribir', async () => {
    vi.mocked(crearCategoria).mockRejectedValue(
      httpError(422, { errors: { nombre: ['Ya existe una categoría con ese nombre.'], 'indicadores.0': ['Texto del indicador inválido.'] } }),
    )
    const usuario = userEvent.setup()
    renderFormulario()
    await completarValido(usuario)

    await usuario.click(screen.getByRole('button', { name: 'Crear categoría' }))

    expect(await screen.findByText('Ya existe una categoría con ese nombre.')).toBeInTheDocument()
    expect(screen.getByText('Texto del indicador inválido.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('Orientación al cliente')
    expect(onGuardado).not.toHaveBeenCalled()

    await usuario.type(screen.getByLabelText('Nombre'), 'x')
    expect(screen.queryByText('Ya existe una categoría con ese nombre.')).not.toBeInTheDocument()
  })

  it('bloquea el guardado mientras hay un envío pendiente y no envía dos veces', async () => {
    vi.mocked(crearCategoria).mockReturnValue(new Promise(() => {}))
    const usuario = userEvent.setup()
    renderFormulario()
    await completarValido(usuario)

    await usuario.click(screen.getByRole('button', { name: 'Crear categoría' }))

    const guardar = await screen.findByRole('button', { name: 'Guardando…' })
    expect(guardar).toBeDisabled()
    expect(crearCategoria).toHaveBeenCalledTimes(1)
  })

  it('muestra un aviso general ante un error inesperado o sin conexión', async () => {
    vi.mocked(crearCategoria).mockRejectedValueOnce(httpError(500)).mockRejectedValueOnce(networkError())
    const usuario = userEvent.setup()
    renderFormulario()
    await completarValido(usuario)

    await usuario.click(screen.getByRole('button', { name: 'Crear categoría' }))
    expect(await screen.findByText('No se pudo guardar la categoría. Inténtalo de nuevo.')).toBeInTheDocument()

    await usuario.click(screen.getByRole('button', { name: 'Crear categoría' }))
    expect(await screen.findByText(/No se pudo conectar con el servidor/)).toBeInTheDocument()
  })

  it('pide confirmación al cancelar con cambios sin guardar y permite seguir editando', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await usuario.type(await screen.findByLabelText('Nombre'), 'Algo')

    await usuario.click(screen.getByRole('button', { name: 'Cancelar' }))

    const confirmacion = await screen.findByRole('alertdialog', { name: '¿Descartar los cambios?' })
    expect(onCerrar).not.toHaveBeenCalled()
    await usuario.click(within(confirmacion).getByRole('button', { name: 'Seguir editando' }))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('Algo')

    await usuario.click(screen.getByRole('button', { name: 'Cancelar' }))
    await usuario.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Descartar cambios' }))
    expect(onCerrar).toHaveBeenCalledTimes(1)
  })

  it('cierra sin preguntar si no hay cambios', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await screen.findByRole('dialog')

    await usuario.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(onCerrar).toHaveBeenCalledTimes(1)
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })
})

describe('CategoriaFormModal · editar', () => {
  it('precarga los datos, muestra el código sin permitir editarlo y envía la versión leída', async () => {
    const usuario = userEvent.setup()
    renderFormulario(categoriaReconocimiento())
    await screen.findByRole('dialog', { name: 'Editar categoría' })

    expect(screen.getByText('Código CAT-001. No se puede modificar.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('Trabajo en equipo')
    expect(screen.getByLabelText('Puntuación base')).toHaveValue(100)
    expect(screen.getByRole('radio', { name: 'Equipo' })).toBeChecked()
    expect(screen.getByLabelText('Indicador 2')).toHaveValue('Ayuda a quien lo necesita')
    expect(screen.queryByLabelText('Estado')).not.toBeInTheDocument()

    await usuario.clear(screen.getByLabelText('Puntuación base'))
    await usuario.type(screen.getByLabelText('Puntuación base'), '250')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    await waitFor(() => expect(editarCategoria).toHaveBeenCalledTimes(1))
    expect(editarCategoria).toHaveBeenCalledWith(1, expect.objectContaining({ puntuacion_base: 250, competencia_id: 10 }), '2026-10-16T14:00:00.123456Z')
  })

  it('muestra una competencia inactiva como valor actual deshabilitado, con la advertencia, y la envía sin cambios', async () => {
    const huerfana = categoriaReconocimiento({
      competencia: { id: 11, codigo: 'COMP-0011', nombre: 'Retirada', estado: 'Inactivo' },
      competencia_activa: false,
      seleccionable: false,
    })
    const usuario = userEvent.setup()
    renderFormulario(huerfana)
    await screen.findByRole('dialog', { name: 'Editar categoría' })

    const opcion = await screen.findByRole('option', { name: 'Retirada (inactiva)' })
    expect(opcion).toBeDisabled()
    expect(screen.getByLabelText('Competencia asociada')).toHaveValue('11')
    expect(screen.getByText('Competencia inactiva')).toBeInTheDocument()
    expect(screen.getByText(/La competencia actual está inactiva y no puede volver a elegirse/)).toBeInTheDocument()

    await usuario.clear(screen.getByLabelText('Nombre'))
    await usuario.type(screen.getByLabelText('Nombre'), 'Nombre corregido')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    await waitFor(() => expect(editarCategoria).toHaveBeenCalledTimes(1))
    expect(vi.mocked(editarCategoria).mock.calls[0][1]).toMatchObject({ competencia_id: 11 })
  })

  it('ante un 409 muestra el mensaje, conserva lo escrito y «Recargar» reemplaza los valores y la versión', async () => {
    vi.mocked(editarCategoria).mockRejectedValueOnce(httpError(409))
    vi.mocked(obtenerCategoria).mockResolvedValue(categoriaReconocimiento({ nombre: 'Versión vigente', updated_at: '2026-10-16T15:30:00.000001Z' }))
    const usuario = userEvent.setup()
    renderFormulario(categoriaReconocimiento())
    await screen.findByRole('dialog', { name: 'Editar categoría' })
    await usuario.clear(screen.getByLabelText('Nombre'))
    await usuario.type(screen.getByLabelText('Nombre'), 'Mi cambio')

    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByText('La categoría fue modificada por otro usuario. Recarga para ver los cambios.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('Mi cambio')

    await usuario.click(screen.getByRole('button', { name: 'Recargar' }))

    await waitFor(() => expect(screen.getByLabelText('Nombre')).toHaveValue('Versión vigente'))
    expect(screen.queryByText('La categoría fue modificada por otro usuario. Recarga para ver los cambios.')).not.toBeInTheDocument()

    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))
    await waitFor(() => expect(editarCategoria).toHaveBeenCalledTimes(2))
    expect(vi.mocked(editarCategoria).mock.calls[1][2]).toBe('2026-10-16T15:30:00.000001Z')
  })

  it('avisa si la categoría ya no existe', async () => {
    vi.mocked(editarCategoria).mockRejectedValue(httpError(404))
    const usuario = userEvent.setup()
    renderFormulario(categoriaReconocimiento())
    await screen.findByRole('dialog', { name: 'Editar categoría' })
    await usuario.type(screen.getByLabelText('Nombre'), '!')

    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByText('La categoría ya no existe.')).toBeInTheDocument()
  })
})
