// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { detalle } from '../../../../test/preguntasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { actualizarPreguntas, obtenerPreguntas } from '../api'
import { PreguntasFormPage } from './PreguntasFormPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  crearPreguntas: vi.fn(),
  actualizarPreguntas: vi.fn(),
  obtenerPreguntas: vi.fn(),
  listarCompetenciasDisponibles: vi.fn(),
}))

function renderEdit() {
  return renderRoutes(
    <>
      <Route path="/preguntas/:competenciaId/editar" element={<PreguntasFormPage />} />
      <Route path="/preguntas" element={<p>Listado</p>} />
    </>,
    '/preguntas/1/editar',
  )
}

beforeEach(() => {
  vi.mocked(actualizarPreguntas).mockReset()
  vi.mocked(obtenerPreguntas).mockReset().mockResolvedValue(detalle())
})

describe('PreguntasFormPage (editar)', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(obtenerPreguntas).mockReturnValue(new Promise(() => {}))
    renderEdit()

    expect(screen.getByRole('status', { name: 'Cargando formulario…' })).toBeInTheDocument()
  })

  it('muestra la competencia en solo lectura, sin selector, y las cuatro preguntas actuales', async () => {
    renderEdit()

    expect(await screen.findByRole('heading', { name: 'Editar preguntas' })).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(screen.getByText('COMP-0001')).toBeInTheDocument()
    expect(screen.getByLabelText('Pregunta 1')).toHaveValue('Cuéntame de una meta que lograste.')
    expect(screen.getByLabelText('Pregunta 4')).toHaveValue('¿Cómo trabajas bajo presión?')
  })

  it('avisa que la competencia asociada está inactiva y permite editar', async () => {
    vi.mocked(obtenerPreguntas).mockResolvedValue(detalle({ estado: 'Inactivo' }))
    renderEdit()

    expect(await screen.findByText('La competencia asociada está inactiva')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Guardar' })).toBeEnabled()
  })

  it('envía los cambios con la versión con la que se abrió y vuelve al listado', async () => {
    vi.mocked(obtenerPreguntas).mockResolvedValue(detalle({ version: 4 }))
    vi.mocked(actualizarPreguntas).mockResolvedValue(detalle({ version: 5 }))
    renderEdit()
    const user = userEvent.setup()

    const campo = await screen.findByLabelText('Pregunta 2')
    await user.clear(campo)
    await user.type(campo, 'Texto nuevo')
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Listado')).toBeInTheDocument()
    expect(actualizarPreguntas).toHaveBeenCalledWith(
      1,
      ['Cuéntame de una meta que lograste.', 'Texto nuevo', 'Describe un error y qué aprendiste.', '¿Cómo trabajas bajo presión?'],
      4,
    )
  })

  it('guardar sin cambios vuelve al listado sin error', async () => {
    vi.mocked(actualizarPreguntas).mockResolvedValue(detalle())
    renderEdit()

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Listado')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('con 409 muestra la alerta de conflicto y Recargar lee la versión actual', async () => {
    vi.mocked(actualizarPreguntas).mockRejectedValue(httpError(409))
    renderEdit()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Guardar' }))
    expect(await screen.findByText('El registro cambió desde que lo abriste. Recarga para ver la versión actual.')).toBeInTheDocument()

    vi.mocked(obtenerPreguntas).mockResolvedValue(detalle({ version: 2, preguntas: ['Texto del servidor', 'b', 'c', 'd'] }))
    await user.click(screen.getByRole('button', { name: 'Recargar registro' }))

    expect(await screen.findByDisplayValue('Texto del servidor')).toBeInTheDocument()
    expect(screen.queryByText(/cambió desde que lo abriste/)).not.toBeInTheDocument()
  })

  it('con 404 indica que el registro ya no existe', async () => {
    vi.mocked(obtenerPreguntas).mockRejectedValue(httpError(404))
    renderEdit()

    expect(await screen.findByText('El registro ya no existe')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver al listado' })).toHaveAttribute('href', '/preguntas')
  })
})
