// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { detalle, puesto } from '../../../../test/puestosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { cambiarEstadoPuesto, obtenerPuesto } from '../api'
import { PuestosList } from './PuestosList'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPuesto: vi.fn(),
  cambiarEstadoPuesto: vi.fn(),
}))

const items = [puesto({ id: 1, codigo: 'PU-001', nombre: 'Analista de datos' })]

function renderLista(esAdmin = true) {
  return renderRoutes(<Route path="/puestos" element={<PuestosList items={items} esAdmin={esAdmin} />} />, '/puestos')
}

async function abrirDetalle(user: ReturnType<typeof userEvent.setup>) {
  await user.click(screen.getByRole('button', { name: /Analista de datos/ }))
  await screen.findByRole('region', { name: 'Información de Analista de datos' })
}

beforeEach(() => {
  vi.mocked(obtenerPuesto).mockReset().mockResolvedValue(detalle())
  vi.mocked(cambiarEstadoPuesto).mockReset().mockResolvedValue(detalle({ estado: 'Inactivo' }))
})

describe('activar y desactivar un puesto desde el detalle', () => {
  it('el botón alterna su texto: Desactivar si está activo y Activar si está inactivo', async () => {
    const activo = renderLista()
    const user = userEvent.setup()
    await abrirDetalle(user)
    expect(screen.getByRole('button', { name: 'Desactivar Analista de datos' })).toHaveTextContent('Desactivar')
    expect(screen.queryByRole('button', { name: /^Activar/ })).not.toBeInTheDocument()
    activo.unmount()

    vi.mocked(obtenerPuesto).mockResolvedValue(detalle({ estado: 'Inactivo' }))
    renderLista()
    await abrirDetalle(userEvent.setup())

    expect(screen.getByRole('button', { name: 'Activar Analista de datos' })).toHaveTextContent('Activar')
    expect(screen.queryByRole('button', { name: /^Desactivar/ })).not.toBeInTheDocument()
  })

  it('solo el Administrador ve el botón y no existe ningún botón de eliminar', async () => {
    const usuario = renderLista(false)
    await abrirDetalle(userEvent.setup())
    expect(screen.queryByRole('button', { name: /^(Activar|Desactivar)/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /eliminar/i })).not.toBeInTheDocument()
    usuario.unmount()

    renderLista(true)
    await abrirDetalle(userEvent.setup())
    expect(screen.getByRole('button', { name: /^Desactivar/ })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /eliminar/i })).not.toBeInTheDocument()
  })

  it('desactivar pide confirmación que nombra el puesto, y Cancelar no cambia nada', async () => {
    renderLista()
    const user = userEvent.setup()
    await abrirDetalle(user)

    await user.click(screen.getByRole('button', { name: 'Desactivar Analista de datos' }))

    const dialogo = await screen.findByRole('alertdialog', { name: '¿Desactivar el puesto "Analista de datos"?' })
    expect(within(dialogo).getByText(/no podrá usarse para crear nuevas posiciones/)).toBeInTheDocument()

    await user.click(within(dialogo).getByRole('button', { name: 'Cancelar' }))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(cambiarEstadoPuesto).not.toHaveBeenCalled()
  })

  it('al confirmar desactiva el puesto y cierra el diálogo', async () => {
    renderLista()
    const user = userEvent.setup()
    await abrirDetalle(user)

    await user.click(screen.getByRole('button', { name: 'Desactivar Analista de datos' }))
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Desactivar' }))

    await waitFor(() => expect(cambiarEstadoPuesto).toHaveBeenCalledWith(1, 'Inactivo'))
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
  })

  it('si el servidor lo rechaza por posiciones en uso muestra el motivo con la cantidad y el puesto sigue activo', async () => {
    vi.mocked(cambiarEstadoPuesto).mockRejectedValue(
      httpError(422, {
        message: 'No se puede desactivar el puesto: tiene 3 posiciones ocupadas o vacantes.',
        errors: { estado: ['No se puede desactivar el puesto: tiene 3 posiciones ocupadas o vacantes.'] },
        posiciones_en_uso: 3,
      }),
    )
    renderLista()
    const user = userEvent.setup()
    await abrirDetalle(user)

    await user.click(screen.getByRole('button', { name: 'Desactivar Analista de datos' }))
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Desactivar' }))

    const dialogo = await screen.findByRole('alertdialog')
    expect(await within(dialogo).findByText('No se puede desactivar el puesto: tiene 3 posiciones ocupadas o vacantes.')).toBeInTheDocument()
    await user.click(within(dialogo).getByRole('button', { name: 'Cancelar' }))
    expect(screen.getByRole('button', { name: 'Desactivar Analista de datos' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Activar/ })).not.toBeInTheDocument()
  })

  it('ante un error de red lo indica dentro del diálogo', async () => {
    vi.mocked(cambiarEstadoPuesto).mockRejectedValue(networkError())
    renderLista()
    const user = userEvent.setup()
    await abrirDetalle(user)

    await user.click(screen.getByRole('button', { name: 'Desactivar Analista de datos' }))
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Desactivar' }))

    expect(await screen.findByText('No se pudo conectar con el servidor. Inténtalo de nuevo.')).toBeInTheDocument()
  })

  it('activar actúa de inmediato, sin pedir confirmación', async () => {
    vi.mocked(obtenerPuesto).mockResolvedValue(detalle({ estado: 'Inactivo' }))
    vi.mocked(cambiarEstadoPuesto).mockResolvedValue(detalle({ estado: 'Activo' }))
    renderLista()
    const user = userEvent.setup()
    await abrirDetalle(user)

    await user.click(screen.getByRole('button', { name: 'Activar Analista de datos' }))

    await waitFor(() => expect(cambiarEstadoPuesto).toHaveBeenCalledWith(1, 'Activo'))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })

  it('si falla al activar muestra un aviso en el detalle', async () => {
    vi.mocked(obtenerPuesto).mockResolvedValue(detalle({ estado: 'Inactivo' }))
    vi.mocked(cambiarEstadoPuesto).mockRejectedValue(httpError(500))
    renderLista()
    const user = userEvent.setup()
    await abrirDetalle(user)

    await user.click(screen.getByRole('button', { name: 'Activar Analista de datos' }))

    expect(await screen.findByText('No se pudo activar el puesto. Inténtalo de nuevo.')).toBeInTheDocument()
  })

  it('mientras la petición está en curso el botón y la confirmación quedan bloqueados', async () => {
    vi.mocked(cambiarEstadoPuesto).mockReturnValue(new Promise(() => {}))
    renderLista()
    const user = userEvent.setup()
    await abrirDetalle(user)

    await user.click(screen.getByRole('button', { name: 'Desactivar Analista de datos' }))
    const dialogo = await screen.findByRole('alertdialog')
    const confirmar = within(dialogo).getByRole('button', { name: 'Desactivar' })
    await user.click(confirmar)

    await waitFor(() => expect(confirmar).toBeDisabled())
    expect(within(dialogo).getByRole('button', { name: 'Cancelar' })).toBeDisabled()
    await user.click(confirmar)
    expect(cambiarEstadoPuesto).toHaveBeenCalledTimes(1)
  })

  it('en un puesto inactivo el detalle avisa que no puede usarse para nuevas posiciones', async () => {
    vi.mocked(obtenerPuesto).mockResolvedValue(detalle({ estado: 'Inactivo' }))
    renderLista()
    await abrirDetalle(userEvent.setup())

    expect(screen.getByText('Este puesto está inactivo: no puede usarse para crear nuevas posiciones.')).toBeInTheDocument()
  })
})
