// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { area } from '../../../../test/catalogoFormativoFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { crearArea, listarAreas, renombrarArea } from '../api'
import { GestionAreasDialog } from './GestionAreasDialog'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarAreas: vi.fn(),
  crearArea: vi.fn(),
  renombrarArea: vi.fn(),
}))

function renderDialogo(onCerrar = vi.fn()) {
  renderRoutes(<Route path="/" element={<GestionAreasDialog onCerrar={onCerrar} />} />, '/')
  return onCerrar
}

beforeEach(() => {
  vi.mocked(listarAreas).mockReset().mockResolvedValue([area({ id: 1, nombre: 'Técnica' }), area({ id: 2, nombre: 'Liderazgo' })])
  vi.mocked(crearArea).mockReset().mockResolvedValue(area({ id: 3, nombre: 'Nueva' }))
  vi.mocked(renombrarArea).mockReset().mockResolvedValue(area({ id: 1, nombre: 'Técnica y datos' }))
})

describe('GestionAreasDialog', () => {
  it('lista las áreas y no ofrece eliminar', async () => {
    renderDialogo()

    expect(await screen.findByText('Técnica')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Renombrar Liderazgo' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Eliminar|Quitar/ })).not.toBeInTheDocument()
  })

  it('crea un área con el nombre normalizado', async () => {
    renderDialogo()
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Nueva área formativa'), '  Desempeño   y resultados ')
    await user.click(screen.getByRole('button', { name: 'Crear área' }))

    await waitFor(() => expect(crearArea).toHaveBeenCalledWith('Desempeño y resultados'))
    expect(await screen.findByText('Área creada.')).toBeInTheDocument()
  })

  it('rechaza un nombre vacío sin llamar al servidor', async () => {
    renderDialogo()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Crear área' }))

    expect(await screen.findByText('Escribe el nombre del área formativa.')).toBeInTheDocument()
    expect(crearArea).not.toHaveBeenCalled()
  })

  it('muestra el error de nombre duplicado que devuelve el servidor', async () => {
    vi.mocked(crearArea).mockRejectedValue(httpError(422, { message: 'x', errors: { nombre: ['Ya existe un área formativa con ese nombre.'] } }))
    renderDialogo()
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Nueva área formativa'), 'Técnica')
    await user.click(screen.getByRole('button', { name: 'Crear área' }))

    expect(await screen.findByText('Ya existe un área formativa con ese nombre.')).toBeInTheDocument()
  })

  it('renombra un área', async () => {
    renderDialogo()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Renombrar Técnica' }))
    const campo = screen.getByLabelText('Nombre del área')
    await user.clear(campo)
    await user.type(campo, 'Técnica y datos')
    await user.click(screen.getByRole('button', { name: 'Guardar nombre' }))

    await waitFor(() => expect(renombrarArea).toHaveBeenCalledWith(1, 'Técnica y datos'))
    expect(await screen.findByText('Nombre actualizado.')).toBeInTheDocument()
  })

  it('Escape y Cerrar cierran el diálogo', async () => {
    const onCerrar = renderDialogo()
    const user = userEvent.setup()

    await screen.findByText('Técnica')
    await user.keyboard('{Escape}')
    await user.click(screen.getByRole('button', { name: 'Cerrar' }))

    expect(onCerrar).toHaveBeenCalledTimes(2)
  })
})
