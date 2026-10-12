// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { detalle } from '../../../../test/competenciasFixtures'
import { obtenerCompetencia } from '../api'
import { GradosPanel } from './GradosPanel'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerCompetencia: vi.fn(),
}))

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <GradosPanel competenciaId={1} nombre="Liderazgo" panelId="grados-1" />
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  vi.mocked(obtenerCompetencia).mockReset()
})

describe('GradosPanel', () => {
  it('muestra un estado de carga propio', () => {
    vi.mocked(obtenerCompetencia).mockReturnValue(new Promise(() => {}))
    renderPanel()

    expect(screen.getByRole('status', { name: 'Cargando grados…' })).toBeInTheDocument()
  })

  it('muestra código, descripción completa y los cuatro grados A–D', async () => {
    vi.mocked(obtenerCompetencia).mockResolvedValue(detalle({ descripcion: 'Descripción completa y larga de la competencia.' }))
    renderPanel()

    expect(await screen.findByText('Descripción completa y larga de la competencia.')).toBeInTheDocument()
    expect(screen.getByText('COMP-0001')).toBeInTheDocument()
    for (const letra of ['A', 'B', 'C', 'D']) {
      expect(screen.getByText(`Grado ${letra}`)).toBeInTheDocument()
      expect(screen.getByText(`Descripción del grado ${letra}.`)).toBeInTheDocument()
    }
    expect(screen.getByRole('region', { name: 'Detalle de Liderazgo' })).toBeInTheDocument()
  })

  it('ante un error ofrece reintentar', async () => {
    vi.mocked(obtenerCompetencia).mockRejectedValueOnce(networkError()).mockResolvedValueOnce(detalle())
    renderPanel()

    expect(await screen.findByText('No se pudieron cargar los grados')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Descripción del grado A.')).toBeInTheDocument()
  })

  it('si la competencia fue eliminada (404) lo informa sin ofrecer reintento', async () => {
    vi.mocked(obtenerCompetencia).mockRejectedValue(httpError(404))
    renderPanel()

    expect(await screen.findByText('La competencia ya no existe.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reintentar' })).not.toBeInTheDocument()
  })
})
