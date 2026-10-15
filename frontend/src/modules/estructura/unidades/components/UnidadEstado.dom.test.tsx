// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { ARBOL_DE_EJEMPLO, detalle, listado } from '../../../../test/unidadesFixtures'
import { useCurrentUser } from '../../../auth/login'
import { cambiarEstadoUnidad, listarUnidades } from '../api'
import { UnidadesPage } from '../pages/UnidadesPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarUnidades: vi.fn(),
  obtenerUnidad: vi.fn(),
  obtenerOpciones: vi.fn(),
  cambiarEstadoUnidad: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function comoRol(role: 'admin' | 'user') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role } } as ReturnType<typeof useCurrentUser>)
}

function renderPage(entrada = '/unidades?estado=Todas') {
  return renderRoutes(<Route path="/unidades" element={<UnidadesPage />} />, entrada)
}

async function esperarArbol() {
  await screen.findByRole('table', { name: 'Unidades organizacionales' })
}

beforeEach(() => {
  vi.mocked(listarUnidades).mockReset().mockResolvedValue(listado())
  vi.mocked(cambiarEstadoUnidad).mockReset().mockResolvedValue(detalle({ id: 7, nombre: 'Ventas', estado: 'Inactiva' }))
  comoRol('admin')
})

describe('desactivar y reactivar una unidad desde el listado', () => {
  it('el botón alterna su texto: Desactivar si está activa y Reactivar si está inactiva', async () => {
    renderPage()
    await esperarArbol()

    expect(screen.getByRole('button', { name: 'Desactivar Ventas' })).toHaveTextContent('Desactivar')
    expect(screen.getByRole('button', { name: 'Reactivar Operaciones' })).toHaveTextContent('Reactivar')
    expect(screen.queryByRole('button', { name: 'Desactivar Operaciones' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reactivar Ventas' })).not.toBeInTheDocument()
  })

  it('el Usuario no ve ninguno de los dos botones ni uno de eliminar', async () => {
    comoRol('user')
    renderPage('/unidades')
    await esperarArbol()

    expect(screen.queryByRole('button', { name: /Desactivar|Reactivar|eliminar/i })).not.toBeInTheDocument()
  })

  it('con unidades dependientes activas o posiciones, Desactivar queda deshabilitado con el motivo visible y descrito', async () => {
    renderPage()
    await esperarArbol()

    // Talento humano: 1 unidad dependiente activa y 3 posiciones vigentes.
    const talento = screen.getByRole('button', { name: 'Desactivar Talento humano' })
    expect(talento).toBeDisabled()
    expect(talento).toHaveAccessibleDescription('No se puede desactivar: tiene 1 unidad dependiente activa y 3 posiciones ocupadas o vacantes.')
    expect(screen.getByText('No se puede desactivar: tiene 1 unidad dependiente activa y 3 posiciones ocupadas o vacantes.')).toBeVisible()

    // Tecnología: solo 2 unidades dependientes activas.
    const tecnologia = screen.getByRole('button', { name: 'Desactivar Tecnología' })
    expect(tecnologia).toBeDisabled()
    expect(tecnologia).toHaveAccessibleDescription('No se puede desactivar: tiene 2 unidades dependientes activas.')
  })

  it('solo posiciones vigentes también bloquean, y sin impedimentos el botón está habilitado sin motivo', async () => {
    vi.mocked(listarUnidades).mockResolvedValue(
      listado([
        { ...ARBOL_DE_EJEMPLO[8], estado: 'Activa', id: 20, codigo: 'UO-020', nombre: 'Compras', posiciones_vigentes: 1 },
        { ...ARBOL_DE_EJEMPLO[7], id: 21, codigo: 'UO-021', nombre: 'Libre' },
      ]),
    )
    renderPage()
    await esperarArbol()

    const compras = screen.getByRole('button', { name: 'Desactivar Compras' })
    expect(compras).toBeDisabled()
    expect(compras).toHaveAccessibleDescription('No se puede desactivar: tiene 1 posición ocupada o vacante.')
    const libre = screen.getByRole('button', { name: 'Desactivar Libre' })
    expect(libre).toBeEnabled()
    expect(libre).not.toHaveAttribute('aria-describedby')
  })

  it('Desactivar pide confirmación que nombra la unidad, y Cancelar no cambia nada', async () => {
    renderPage()
    const user = userEvent.setup()
    await esperarArbol()

    await user.click(screen.getByRole('button', { name: 'Desactivar Ventas' }))

    const dialogo = await screen.findByRole('alertdialog', { name: '¿Desactivar la unidad "Ventas"?' })
    expect(within(dialogo).getByText(/no podrá recibir nuevas posiciones ni unidades dependientes/)).toBeInTheDocument()
    await user.click(within(dialogo).getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(cambiarEstadoUnidad).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: 'Desactivar Ventas' })).toHaveFocus()
  })

  it('Escape cierra el diálogo sin desactivar', async () => {
    renderPage()
    const user = userEvent.setup()
    await esperarArbol()
    await user.click(screen.getByRole('button', { name: 'Desactivar Ventas' }))
    await screen.findByRole('alertdialog')

    await user.keyboard('{Escape}')

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(cambiarEstadoUnidad).not.toHaveBeenCalled()
  })

  it('al confirmar desactiva la unidad, cierra el diálogo, avisa y recarga el árbol', async () => {
    renderPage()
    const user = userEvent.setup()
    await esperarArbol()
    const lecturas = vi.mocked(listarUnidades).mock.calls.length
    await user.click(screen.getByRole('button', { name: 'Desactivar Ventas' }))

    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Desactivar' }))

    expect(await screen.findByText('Unidad desactivada.')).toBeInTheDocument()
    expect(cambiarEstadoUnidad).toHaveBeenCalledWith(7, 'Inactiva')
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    await waitFor(() => expect(vi.mocked(listarUnidades).mock.calls.length).toBeGreaterThan(lecturas))
  })

  it('si el servidor rechaza con cantidades las muestra en el diálogo, que sigue abierto', async () => {
    const mensaje = 'No se puede desactivar la unidad: tiene 2 unidades dependientes activas y 3 posiciones ocupadas o vacantes.'
    vi.mocked(cambiarEstadoUnidad).mockRejectedValue(httpError(422, { message: mensaje, errors: { estado: [mensaje] }, dependientes_activas: 2, posiciones_vigentes: 3 }))
    renderPage()
    const user = userEvent.setup()
    await esperarArbol()
    await user.click(screen.getByRole('button', { name: 'Desactivar Ventas' }))

    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Desactivar' }))

    expect(await within(screen.getByRole('alertdialog')).findByText(mensaje)).toBeInTheDocument()
    expect(screen.queryByText('Unidad desactivada.')).not.toBeInTheDocument()
  })

  it('el listado se vuelve a leer también tras el rechazo', async () => {
    vi.mocked(cambiarEstadoUnidad).mockRejectedValue(httpError(422, { message: 'No se puede desactivar la unidad: tiene 1 unidad dependiente activa.' }))
    renderPage()
    const user = userEvent.setup()
    await esperarArbol()
    const lecturas = vi.mocked(listarUnidades).mock.calls.length
    await user.click(screen.getByRole('button', { name: 'Desactivar Ventas' }))

    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Desactivar' }))

    await waitFor(() => expect(vi.mocked(listarUnidades).mock.calls.length).toBeGreaterThan(lecturas))
  })

  it('avisa si la unidad ya no existe o no hay conexión', async () => {
    vi.mocked(cambiarEstadoUnidad).mockRejectedValueOnce(httpError(404, { message: 'La unidad no existe.' })).mockRejectedValueOnce(networkError())
    renderPage()
    const user = userEvent.setup()
    await esperarArbol()
    await user.click(screen.getByRole('button', { name: 'Desactivar Ventas' }))

    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Desactivar' }))
    expect(await within(screen.getByRole('alertdialog')).findByText('La unidad ya no existe.')).toBeInTheDocument()

    await user.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Desactivar' }))
    expect(await within(screen.getByRole('alertdialog')).findByText('No se pudo conectar con el servidor. Inténtalo de nuevo.')).toBeInTheDocument()
  })

  it('Reactivar actúa sin diálogo y avisa', async () => {
    vi.mocked(cambiarEstadoUnidad).mockResolvedValue(detalle({ id: 8, nombre: 'Operaciones', estado: 'Activa' }))
    renderPage()
    const user = userEvent.setup()
    await esperarArbol()

    await user.click(screen.getByRole('button', { name: 'Reactivar Operaciones' }))

    expect(await screen.findByText('Unidad reactivada.')).toBeInTheDocument()
    expect(cambiarEstadoUnidad).toHaveBeenCalledWith(8, 'Activa')
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })

  it('si no procede reactivar muestra el motivo del servidor en un aviso', async () => {
    const mensaje = 'No se puede reactivar la unidad: su unidad padre está inactiva.'
    vi.mocked(cambiarEstadoUnidad).mockRejectedValue(httpError(422, { message: mensaje, errors: { estado: [mensaje] }, motivo: 'padre_inactivo' }))
    renderPage()
    const user = userEvent.setup()
    await esperarArbol()

    await user.click(screen.getByRole('button', { name: 'Reactivar Operaciones' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(mensaje)
    expect(screen.queryByText('Unidad reactivada.')).not.toBeInTheDocument()
  })

  it('bloquea el botón de la fila mientras se cambia su estado', async () => {
    vi.mocked(cambiarEstadoUnidad).mockReturnValue(new Promise(() => {}))
    renderPage()
    const user = userEvent.setup()
    await esperarArbol()

    await user.click(screen.getByRole('button', { name: 'Reactivar Operaciones' }))

    await waitFor(() => expect(screen.getByRole('button', { name: 'Reactivar Operaciones' })).toBeDisabled())
    expect(screen.getByRole('button', { name: 'Reactivar Operaciones' })).toHaveAttribute('aria-busy', 'true')
  })
})
