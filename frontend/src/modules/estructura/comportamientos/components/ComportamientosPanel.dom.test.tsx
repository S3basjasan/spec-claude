// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { detalle } from '../../../../test/comportamientosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerComportamientos } from '../api'
import { ComportamientosPanel } from './ComportamientosPanel'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerComportamientos: vi.fn(),
}))

function renderPanel() {
  return renderRoutes(<Route path="/" element={<ComportamientosPanel competenciaId={1} nombre="Liderazgo" panelId="panel-1" />} />, '/')
}

beforeEach(() => {
  vi.mocked(obtenerComportamientos).mockReset().mockResolvedValue(detalle())
})

describe('ComportamientosPanel', () => {
  it('muestra los cuatro grados con sus comportamientos como viñetas', async () => {
    renderPanel()

    const region = within(await screen.findByRole('region', { name: 'Comportamientos de Liderazgo' }))
    for (const letra of ['A', 'B', 'C', 'D']) {
      expect(region.getByRole('heading', { name: `Grado ${letra}` })).toBeInTheDocument()
    }
    const listas = region.getAllByRole('list')
    expect(listas).toHaveLength(4)
    expect(within(listas[0]).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Inspira una visión compartida.',
      'Desarrolla a otros líderes.',
    ])
    expect(within(listas[3]).getByText('Sigue las indicaciones.')).toBeInTheDocument()
  })

  it('muestra un estado de carga', () => {
    vi.mocked(obtenerComportamientos).mockReturnValue(new Promise(() => {}))
    renderPanel()

    expect(screen.getByRole('status', { name: 'Cargando comportamientos…' })).toBeInTheDocument()
  })

  it('muestra el error con Reintentar', async () => {
    vi.mocked(obtenerComportamientos).mockRejectedValueOnce(httpError(500)).mockResolvedValue(detalle())
    renderPanel()

    expect(await screen.findByText('No se pudieron cargar los comportamientos')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('region')).toBeInTheDocument()
  })

  it('con 404 indica que el registro ya no existe y no ofrece reintentar', async () => {
    vi.mocked(obtenerComportamientos).mockRejectedValue(httpError(404))
    renderPanel()

    expect(await screen.findByText('El registro ya no existe.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reintentar' })).not.toBeInTheDocument()
  })

  it('avisa que la competencia asociada está inactiva solo cuando corresponde', async () => {
    renderPanel()
    await screen.findByRole('region')
    expect(screen.queryByText('La competencia asociada está inactiva')).not.toBeInTheDocument()
  })

  it('muestra el aviso neutro con la competencia inactiva y conserva los comportamientos', async () => {
    vi.mocked(obtenerComportamientos).mockResolvedValue(detalle({ estado: 'Inactivo' }))
    renderPanel()

    expect(await screen.findByText('La competencia asociada está inactiva')).toBeInTheDocument()
    expect(screen.getByText('Dirige equipos con claridad.')).toBeInTheDocument()
  })
})
