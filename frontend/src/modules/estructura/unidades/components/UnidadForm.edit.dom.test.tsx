// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { ARBOL_DE_EJEMPLO, detalleConAuditoria, listado, opciones, TIPOS } from '../../../../test/unidadesFixtures'
import { useCurrentUser } from '../../../auth/login'
import { actualizarUnidad, listarUnidades, obtenerOpciones, obtenerUnidad } from '../api'
import { UnidadesPage } from '../pages/UnidadesPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarUnidades: vi.fn(),
  obtenerUnidad: vi.fn(),
  obtenerOpciones: vi.fn(),
  actualizarUnidad: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

const GENERAL = { id: 1, codigo: 'UO-001', nombre: 'Gerencia general' }

/** Ventas (UO-007): un Departamento bajo la raíz, en su versión 3. */
const ventas = detalleConAuditoria({
  id: 7,
  codigo: 'UO-007',
  nombre: 'Ventas',
  tipo: TIPOS.Departamento,
  unidad_padre: GENERAL,
  nivel: 1,
  version: 3,
})

function renderPage() {
  return renderRoutes(<Route path="/unidades" element={<UnidadesPage />} />, '/unidades')
}

async function abrirEdicion(user: ReturnType<typeof userEvent.setup>, nombre = 'Ventas') {
  await screen.findByRole('table', { name: 'Unidades organizacionales' })
  await user.click(screen.getByRole('button', { name: `Editar ${nombre}` }))
}

beforeEach(() => {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role: 'admin' } } as ReturnType<typeof useCurrentUser>)
  vi.mocked(listarUnidades).mockReset().mockResolvedValue(listado(ARBOL_DE_EJEMPLO.filter((u) => u.estado === 'Activa')))
  vi.mocked(obtenerUnidad).mockReset().mockResolvedValue(ventas)
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opciones())
  vi.mocked(actualizarUnidad).mockReset().mockResolvedValue(ventas)
})

describe('UnidadForm (editar)', () => {
  it('abre el formulario con el título "Editar unidad UO-###" y los valores actuales', async () => {
    renderPage()
    await abrirEdicion(userEvent.setup())

    const formulario = await screen.findByRole('form', { name: 'Editar unidad UO-007' })
    expect(screen.getByRole('textbox', { name: 'Nombre' })).toHaveValue('Ventas')
    expect(screen.getByRole('combobox', { name: 'Unidad padre' })).toHaveValue('1')
    expect(screen.getByRole('combobox', { name: 'Tipo de unidad' })).toHaveValue('2')
    expect(within(formulario).queryByLabelText(/estado/i)).not.toBeInTheDocument()
  })

  it('lee de nuevo el detalle y pide las opciones excluyendo la propia unidad', async () => {
    renderPage()
    await abrirEdicion(userEvent.setup())
    await screen.findByRole('form', { name: 'Editar unidad UO-007' })

    expect(obtenerUnidad).toHaveBeenCalledWith(7)
    expect(obtenerOpciones).toHaveBeenCalledWith(7)
  })

  it('muestra en solo lectura el código, las fechas, las cantidades y la auditoría', async () => {
    renderPage()
    await abrirEdicion(userEvent.setup())

    const info = await screen.findByRole('group', { name: 'Datos de la unidad' })
    expect(within(info).getByText('UO-007')).toBeInTheDocument()
    expect(within(info).getByText('Fecha de creación')).toBeInTheDocument()
    expect(within(info).getByText('Fecha de supresión')).toBeInTheDocument()
    expect(within(info).getByText('Creada por').nextElementSibling).toHaveTextContent(/Ana Administradora/)
  })

  it('el selector solo ofrece lo que el servidor devuelve: no incluye la unidad ni sus dependientes', async () => {
    const sinRama = opciones({ unidades: opciones().unidades.filter((u) => ![3, 4, 5].includes(u.id)) })
    vi.mocked(obtenerOpciones).mockResolvedValue(sinRama)
    vi.mocked(obtenerUnidad).mockResolvedValue({ ...ventas, id: 3, codigo: 'UO-003', nombre: 'Tecnología' })
    renderPage()
    await abrirEdicion(userEvent.setup(), 'Tecnología')

    const padres = within(await screen.findByRole('combobox', { name: 'Unidad padre' })).getAllByRole('option')
    const nombres = padres.map((opcion) => opcion.textContent?.trim())
    expect(nombres).not.toContain('Tecnología (UO-003)')
    expect(nombres).not.toContain('Desarrollo (UO-005)')
    expect(nombres).not.toContain('Inteligencia de negocio (UO-004)')
    expect(nombres).toContain('Finanzas (UO-006)')
  })

  it('"Ninguna (unidad raíz)" queda habilitada y seleccionada si la unidad ya es raíz', async () => {
    vi.mocked(obtenerUnidad).mockResolvedValue({ ...ventas, id: 1, codigo: 'UO-001', nombre: 'Gerencia general', unidad_padre: null, nivel: 0 })
    vi.mocked(obtenerOpciones).mockResolvedValue(opciones({ hay_otra_raiz_activa: false }))
    renderPage()
    await abrirEdicion(userEvent.setup(), 'Gerencia general')

    const padre = await screen.findByRole('combobox', { name: 'Unidad padre' })
    expect(padre).toHaveValue('')
    expect(within(padre).getByRole('option', { name: 'Ninguna (unidad raíz)' })).toBeEnabled()
  })

  it('una raíz inactiva conserva "Ninguna (unidad raíz)" aunque ya exista otra raíz activa', async () => {
    vi.mocked(obtenerUnidad).mockResolvedValue({ ...ventas, estado: 'Inactiva', unidad_padre: null, nivel: 0, fecha_supresion: '2026-10-20' })
    vi.mocked(obtenerOpciones).mockResolvedValue(opciones({ hay_otra_raiz_activa: true }))
    renderPage()
    await abrirEdicion(userEvent.setup())

    expect(await screen.findByRole('option', { name: 'Ninguna (unidad raíz)' })).toBeEnabled()
    expect(screen.getByRole('combobox', { name: 'Unidad padre' })).toHaveValue('')
  })

  it('una unidad con otro padre no puede quedar sin padre si ya existe una raíz activa', async () => {
    renderPage()
    await abrirEdicion(userEvent.setup())

    expect(await screen.findByRole('option', { name: 'Ninguna (unidad raíz)' })).toBeDisabled()
  })

  it('conserva como opción adicional un padre actual que está inactivo', async () => {
    vi.mocked(obtenerUnidad).mockResolvedValue({ ...ventas, unidad_padre: { id: 8, codigo: 'UO-008', nombre: 'Operaciones' } })
    renderPage()
    await abrirEdicion(userEvent.setup())

    const padre = await screen.findByRole('combobox', { name: 'Unidad padre' })
    expect(padre).toHaveValue('8')
    expect(within(padre).getByRole('option', { name: 'Operaciones (UO-008) (inactiva)' })).toBeInTheDocument()
  })

  it('envía los cambios con la versión con la que se abrió el formulario y cierra con "Unidad actualizada."', async () => {
    renderPage()
    const user = userEvent.setup()
    await abrirEdicion(user)
    const nombre = await screen.findByRole('textbox', { name: 'Nombre' })

    await user.clear(nombre)
    await user.type(nombre, 'Comercial')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Unidad padre' }), '3')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Tipo de unidad' }), '3')
    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    expect(await screen.findByText('Unidad actualizada.')).toBeInTheDocument()
    expect(actualizarUnidad).toHaveBeenCalledWith(7, { nombre: 'Comercial', tipo_unidad_id: 3, unidad_padre_id: 3 }, 3)
    expect(screen.queryByRole('form', { name: 'Editar unidad UO-007' })).not.toBeInTheDocument()
  })

  it('guarda con la versión de apertura aunque el detalle se vuelva a leer con una versión más nueva', async () => {
    const { client } = renderPage()
    const user = userEvent.setup()
    await abrirEdicion(user)
    await screen.findByRole('form', { name: 'Editar unidad UO-007' })

    vi.mocked(obtenerUnidad).mockResolvedValue({ ...ventas, version: 4, nombre: 'Cambiada por otra persona' })
    await client.invalidateQueries({ queryKey: ['unidades'] })
    await user.type(screen.getByRole('textbox', { name: 'Nombre' }), ' Norte')
    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    await waitFor(() => expect(actualizarUnidad).toHaveBeenCalled())
    expect(vi.mocked(actualizarUnidad).mock.calls[0][2]).toBe(3)
    expect(vi.mocked(actualizarUnidad).mock.calls[0][1].nombre).toBe('Ventas Norte')
  })

  it('al reabrir la edición usa la versión vigente y no la que quedó en caché', async () => {
    renderPage()
    const user = userEvent.setup()
    await abrirEdicion(user)
    await user.type(await screen.findByRole('textbox', { name: 'Nombre' }), ' Norte')
    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))
    await screen.findByText('Unidad actualizada.')

    // Tras el guardado el servidor tiene la versión 4; el detalle en caché sigue en la 3.
    vi.mocked(obtenerUnidad).mockResolvedValue({ ...ventas, nombre: 'Ventas Norte', version: 4 })
    await user.click(screen.getByRole('button', { name: 'Editar Ventas' }))
    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Nombre' })).toHaveValue('Ventas Norte'))
    await user.type(screen.getByRole('textbox', { name: 'Nombre' }), ' 2')
    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    await waitFor(() => expect(actualizarUnidad).toHaveBeenCalledTimes(2))
    expect(vi.mocked(actualizarUnidad).mock.calls[1][2]).toBe(4)
  })

  it('guardar sin cambios cierra el formulario sin error', async () => {
    renderPage()
    const user = userEvent.setup()
    await abrirEdicion(user)
    await screen.findByRole('form', { name: 'Editar unidad UO-007' })

    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    await waitFor(() => expect(screen.queryByRole('form', { name: 'Editar unidad UO-007' })).not.toBeInTheDocument())
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('ante un conflicto 409 avisa y "Recargar unidad" muestra la versión actual para volver a intentar', async () => {
    vi.mocked(actualizarUnidad).mockRejectedValueOnce(
      httpError(409, { message: 'La unidad cambió desde que la abriste. Recarga para ver la versión actual.' }),
    )
    renderPage()
    const user = userEvent.setup()
    await abrirEdicion(user)
    await user.type(await screen.findByRole('textbox', { name: 'Nombre' }), ' Norte')
    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    expect(await screen.findByText('La unidad cambió desde que la abriste. Recarga para ver la versión actual.')).toBeInTheDocument()

    vi.mocked(obtenerUnidad).mockResolvedValue({ ...ventas, nombre: 'Ventas y mercadeo', version: 4 })
    await user.click(screen.getByRole('button', { name: 'Recargar unidad' }))

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Nombre' })).toHaveValue('Ventas y mercadeo'))
    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))
    await waitFor(() => expect(actualizarUnidad).toHaveBeenCalledTimes(2))
    expect(vi.mocked(actualizarUnidad).mock.calls[1][2]).toBe(4)
  })

  it('si la unidad ya no existe al guardar cierra el formulario y lo avisa', async () => {
    vi.mocked(actualizarUnidad).mockRejectedValue(httpError(404, { message: 'La unidad no existe.' }))
    renderPage()
    const user = userEvent.setup()
    await abrirEdicion(user)
    await user.type(await screen.findByRole('textbox', { name: 'Nombre' }), ' Norte')
    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    expect(await screen.findByText('La unidad ya no existe.')).toBeInTheDocument()
    expect(screen.queryByRole('form', { name: 'Editar unidad UO-007' })).not.toBeInTheDocument()
  })

  it('si la unidad ya no existe al abrir el formulario lo avisa y permite cerrarlo', async () => {
    vi.mocked(obtenerUnidad).mockRejectedValue(httpError(404, { message: 'La unidad no existe.' }))
    renderPage()
    const user = userEvent.setup()
    await abrirEdicion(user)

    expect(await screen.findByText('La unidad ya no existe.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Cancelar' }))
    expect(screen.queryByText('La unidad ya no existe.')).not.toBeInTheDocument()
  })

  it('muestra un esqueleto mientras carga y un error con Reintentar si no pudo cargar', async () => {
    vi.mocked(obtenerOpciones).mockRejectedValueOnce(httpError(500)).mockResolvedValue(opciones())
    renderPage()
    const user = userEvent.setup()
    await abrirEdicion(user)

    expect(await screen.findByText('No se pudo cargar el formulario')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('form', { name: 'Editar unidad UO-007' })).toBeInTheDocument()
  })

  it('editar otra fila cambia el formulario abierto', async () => {
    renderPage()
    const user = userEvent.setup()
    await abrirEdicion(user)
    await screen.findByRole('form', { name: 'Editar unidad UO-007' })
    vi.mocked(obtenerUnidad).mockResolvedValue({ ...ventas, id: 6, codigo: 'UO-006', nombre: 'Finanzas' })

    await user.click(screen.getByRole('button', { name: 'Editar Finanzas' }))

    expect(await screen.findByRole('form', { name: 'Editar unidad UO-006' })).toBeInTheDocument()
    expect(screen.getAllByRole('form')).toHaveLength(1)
  })

  it('devuelve el foco al botón Editar al cancelar', async () => {
    renderPage()
    const user = userEvent.setup()
    await abrirEdicion(user)
    await screen.findByRole('form', { name: 'Editar unidad UO-007' })

    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(screen.getByRole('button', { name: 'Editar Ventas' })).toHaveFocus()
  })
})
