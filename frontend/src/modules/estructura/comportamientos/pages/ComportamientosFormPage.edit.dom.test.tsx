// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { detalle } from '../../../../test/comportamientosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { actualizarComportamientos, obtenerComportamientos } from '../api'
import { ComportamientosFormPage } from './ComportamientosFormPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  crearComportamientos: vi.fn(),
  actualizarComportamientos: vi.fn(),
  obtenerComportamientos: vi.fn(),
  listarCompetenciasDisponibles: vi.fn(),
}))

function renderEdit() {
  return renderRoutes(
    <>
      <Route path="/comportamientos/:competenciaId/editar" element={<ComportamientosFormPage />} />
      <Route path="/comportamientos" element={<p>Listado</p>} />
    </>,
    '/comportamientos/1/editar',
  )
}

beforeEach(() => {
  vi.mocked(actualizarComportamientos).mockReset()
  vi.mocked(obtenerComportamientos).mockReset().mockResolvedValue(detalle())
})

describe('ComportamientosFormPage (editar)', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(obtenerComportamientos).mockReturnValue(new Promise(() => {}))
    renderEdit()

    expect(screen.getByRole('status', { name: 'Cargando formulario…' })).toBeInTheDocument()
  })

  it('muestra la competencia en solo lectura, sin selector, y los comportamientos actuales por grado', async () => {
    renderEdit()

    expect(await screen.findByRole('heading', { name: 'Editar comportamientos' })).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(screen.getByText('COMP-0001')).toBeInTheDocument()
    expect(screen.getByLabelText('Comportamiento 1 del grado A')).toHaveValue('Inspira una visión compartida.')
    expect(screen.getByLabelText('Comportamiento 2 del grado A')).toHaveValue('Desarrolla a otros líderes.')
    expect(screen.getByLabelText('Comportamiento 1 del grado D')).toHaveValue('Sigue las indicaciones.')
  })

  it('avisa que la competencia asociada está inactiva y permite editar', async () => {
    vi.mocked(obtenerComportamientos).mockResolvedValue(detalle({ estado: 'Inactivo' }))
    renderEdit()

    expect(await screen.findByText('La competencia asociada está inactiva')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Guardar' })).toBeEnabled()
  })

  it('envía los cambios con la versión con la que se abrió y vuelve al listado', async () => {
    vi.mocked(obtenerComportamientos).mockResolvedValue(detalle({ version: 4 }))
    vi.mocked(actualizarComportamientos).mockResolvedValue(detalle({ version: 5 }))
    renderEdit()
    const user = userEvent.setup()

    const campo = await screen.findByLabelText('Comportamiento 1 del grado B')
    await user.clear(campo)
    await user.type(campo, 'Texto nuevo')
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Listado')).toBeInTheDocument()
    expect(actualizarComportamientos).toHaveBeenCalledWith(
      1,
      {
        A: ['Inspira una visión compartida.', 'Desarrolla a otros líderes.'],
        B: ['Texto nuevo'],
        C: ['Orienta a su grupo.'],
        D: ['Sigue las indicaciones.'],
      },
      4,
    )
  })

  it('guardar sin cambios vuelve al listado sin error', async () => {
    vi.mocked(actualizarComportamientos).mockResolvedValue(detalle())
    renderEdit()

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Listado')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('con 409 muestra la alerta de conflicto y Recargar lee la versión actual', async () => {
    vi.mocked(actualizarComportamientos).mockRejectedValue(httpError(409))
    renderEdit()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Guardar' }))
    expect(await screen.findByText('El registro cambió desde que lo abriste. Recarga para ver la versión actual.')).toBeInTheDocument()

    vi.mocked(obtenerComportamientos).mockResolvedValue(detalle({ version: 2, comportamientos: { A: ['Texto del servidor'], B: ['b'], C: ['c'], D: ['d'] } }))
    await user.click(screen.getByRole('button', { name: 'Recargar registro' }))

    expect(await screen.findByDisplayValue('Texto del servidor')).toBeInTheDocument()
    expect(screen.queryByText(/cambió desde que lo abriste/)).not.toBeInTheDocument()
  })

  it('con 404 indica que el registro ya no existe', async () => {
    vi.mocked(obtenerComportamientos).mockRejectedValue(httpError(404))
    renderEdit()

    expect(await screen.findByText('El registro ya no existe')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver al listado' })).toHaveAttribute('href', '/comportamientos')
  })
})
