// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { detalle } from '../../../../test/preguntasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerPreguntas } from '../api'
import { PreguntasPanel } from './PreguntasPanel'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPreguntas: vi.fn(),
}))

function renderPanel() {
  return renderRoutes(<Route path="/" element={<PreguntasPanel competenciaId={1} nombre="Liderazgo" panelId="panel-1" />} />, '/')
}

beforeEach(() => {
  vi.mocked(obtenerPreguntas).mockReset().mockResolvedValue(detalle())
})

describe('PreguntasPanel', () => {
  it('muestra las cuatro preguntas numeradas del 1 al 4 en una lista ordenada', async () => {
    renderPanel()

    const region = within(await screen.findByRole('region', { name: 'Preguntas de Liderazgo' }))
    const lista = region.getByRole('list')
    expect(lista.tagName).toBe('OL')
    expect(within(lista).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Cuéntame de una meta que lograste.',
      '¿Cómo organizas tu trabajo?',
      'Describe un error y qué aprendiste.',
      '¿Cómo trabajas bajo presión?',
    ])
  })

  it('muestra un estado de carga', () => {
    vi.mocked(obtenerPreguntas).mockReturnValue(new Promise(() => {}))
    renderPanel()

    expect(screen.getByRole('status', { name: 'Cargando preguntas…' })).toBeInTheDocument()
  })

  it('muestra el error con Reintentar', async () => {
    vi.mocked(obtenerPreguntas).mockRejectedValueOnce(httpError(500)).mockResolvedValue(detalle())
    renderPanel()

    expect(await screen.findByText('No se pudieron cargar las preguntas')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('region')).toBeInTheDocument()
  })

  it('con 404 indica que el registro ya no existe y no ofrece reintentar', async () => {
    vi.mocked(obtenerPreguntas).mockRejectedValue(httpError(404))
    renderPanel()

    expect(await screen.findByText('El registro ya no existe.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reintentar' })).not.toBeInTheDocument()
  })

  it('avisa que la competencia asociada está inactiva solo cuando corresponde', async () => {
    renderPanel()
    await screen.findByRole('region')
    expect(screen.queryByText('La competencia asociada está inactiva')).not.toBeInTheDocument()
  })

  it('muestra el aviso neutro con la competencia inactiva y conserva las preguntas', async () => {
    vi.mocked(obtenerPreguntas).mockResolvedValue(detalle({ estado: 'Inactivo' }))
    renderPanel()

    expect(await screen.findByText('La competencia asociada está inactiva')).toBeInTheDocument()
    expect(screen.getByText('¿Cómo organizas tu trabajo?')).toBeInTheDocument()
  })
})
