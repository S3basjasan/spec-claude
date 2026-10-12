// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { detalle } from '../../../../test/competenciasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { actualizarCompetencia, obtenerCompetencia } from '../api'
import { CompetenciaFormPage } from './CompetenciaFormPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  crearCompetencia: vi.fn(),
  actualizarCompetencia: vi.fn(),
  obtenerCompetencia: vi.fn(),
}))

function renderEditar() {
  return renderRoutes(
    <>
      <Route path="/competencias/:id/editar" element={<CompetenciaFormPage />} />
      <Route path="/competencias" element={<p>Listado</p>} />
    </>,
    '/competencias/1/editar',
  )
}

beforeEach(() => {
  vi.mocked(obtenerCompetencia).mockReset().mockResolvedValue(detalle({ version: 3 }))
  vi.mocked(actualizarCompetencia).mockReset()
})

describe('CompetenciaFormPage (editar)', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(obtenerCompetencia).mockReturnValue(new Promise(() => {}))
    renderEditar()

    expect(screen.getByRole('status', { name: 'Cargando competencia…' })).toBeInTheDocument()
  })

  it('carga los valores actuales y muestra el código como solo lectura', async () => {
    renderEditar()

    expect(await screen.findByRole('heading', { name: 'Editar competencia' })).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('Liderazgo')
    expect(screen.getByLabelText('Tipo')).toHaveValue('Cardinal')
    expect(screen.getByLabelText('Grado B')).toHaveValue('Descripción del grado B.')
    const codigo = screen.getByLabelText('Código')
    expect(codigo).toHaveValue('COMP-0001')
    expect(codigo).toHaveAttribute('readonly')
  })

  it('guarda enviando la versión con la que se abrió y vuelve al listado', async () => {
    vi.mocked(actualizarCompetencia).mockResolvedValue(detalle({ version: 4 }))
    renderEditar()
    const user = userEvent.setup()
    await screen.findByLabelText('Nombre')

    await user.clear(screen.getByLabelText('Nombre'))
    await user.type(screen.getByLabelText('Nombre'), 'Liderazgo situacional')
    await user.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByText('Listado')).toBeInTheDocument()
    const [id, datos, version] = vi.mocked(actualizarCompetencia).mock.calls[0]
    expect(id).toBe(1)
    expect(datos.nombre).toBe('Liderazgo situacional')
    expect(version).toBe(3)
  })

  it('ante un conflicto (409) avisa y "Recargar" trae la versión actual', async () => {
    vi.mocked(actualizarCompetencia).mockRejectedValue(httpError(409, { message: 'cambió' }))
    renderEditar()
    const user = userEvent.setup()
    await screen.findByLabelText('Nombre')

    await user.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByText('La competencia cambió desde que la abriste. Recarga para ver la versión actual.')).toBeInTheDocument()

    vi.mocked(obtenerCompetencia).mockResolvedValue(detalle({ version: 4, nombre: 'Nombre nuevo del servidor' }))
    await user.click(screen.getByRole('button', { name: 'Recargar competencia' }))

    await waitFor(() => expect(screen.getByLabelText('Nombre')).toHaveValue('Nombre nuevo del servidor'))
    expect(screen.queryByText(/La competencia cambió/)).not.toBeInTheDocument()
  })

  it('si la competencia ya no existe (404) lo informa con enlace al listado', async () => {
    vi.mocked(obtenerCompetencia).mockRejectedValue(httpError(404))
    renderEditar()

    expect(await screen.findByRole('heading', { name: 'La competencia ya no existe' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver al listado' })).toHaveAttribute('href', '/competencias')
  })

  it('ante un error de carga ofrece reintentar', async () => {
    vi.mocked(obtenerCompetencia).mockRejectedValueOnce(networkError()).mockResolvedValueOnce(detalle())
    renderEditar()

    expect(await screen.findByText('No se pudo cargar la competencia')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByLabelText('Nombre')).toHaveValue('Liderazgo')
  })

  it('mapea un 422 al campo correspondiente', async () => {
    vi.mocked(actualizarCompetencia).mockRejectedValue(httpError(422, { errors: { nombre: ['Ya existe una competencia con ese nombre.'] } }))
    renderEditar()
    const user = userEvent.setup()
    await screen.findByLabelText('Nombre')

    await user.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByText('Ya existe una competencia con ese nombre.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveAttribute('aria-invalid', 'true')
  })
})
