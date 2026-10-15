// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { area, detalle, fila, recomendado, recurso } from '../../../../test/catalogoFormativoFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarCatalogoCompleto, listarRecomendados, obtenerDetalle } from '../api'
import { CatalogoColaboradorPage } from './CatalogoColaboradorPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarCatalogoCompleto: vi.fn(),
  listarRecomendados: vi.fn(),
  obtenerDetalle: vi.fn(),
}))

function renderPage(entrada = '/formacion') {
  return renderRoutes(<Route path="/formacion" element={<CatalogoColaboradorPage />} />, entrada)
}

const filas = [
  fila({ competencia_id: 1, nombre: 'Comunicación efectiva', nivel_actual: 'B', cursos_count: 2 }),
  fila({ competencia_id: 2, nombre: 'Liderazgo', area_formativa: area({ id: 2, nombre: 'Liderazgo y gestión' }), cursos_count: 0, nivel_actual: null }),
]

beforeEach(() => {
  vi.mocked(listarCatalogoCompleto).mockReset().mockResolvedValue(filas)
  vi.mocked(listarRecomendados).mockReset().mockResolvedValue([])
  vi.mocked(obtenerDetalle).mockReset().mockResolvedValue(detalle({ nivel_actual: 'B', nivel_requerido: 'A' }))
})

describe('CatalogoColaboradorPage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(listarCatalogoCompleto).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando catálogo…' })).toBeInTheDocument()
  })

  it('muestra las tarjetas agrupadas por área, sin controles de gestión ni cobertura', async () => {
    renderPage()

    expect(await screen.findByRole('heading', { name: 'Habilidades interpersonales' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Liderazgo y gestión' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Comunicación efectiva/ })).toHaveTextContent('Tu nivel: B')
    expect(screen.queryByRole('button', { name: /Guardar|Asociar|Ver detalle/ })).not.toBeInTheDocument()
    expect(screen.queryByText(/Cobertura formativa/)).not.toBeInTheDocument()
  })

  it('no muestra la sección de recomendados cuando no hay', async () => {
    renderPage()
    await screen.findByRole('heading', { name: 'Habilidades interpersonales' })

    expect(screen.queryByText('Recomendados para cerrar mis brechas')).not.toBeInTheDocument()
  })

  it('muestra los recomendados arriba cuando existen', async () => {
    vi.mocked(listarRecomendados).mockResolvedValue([recomendado()])
    renderPage()

    const seccion = await screen.findByRole('region', { name: 'Recomendados para cerrar mis brechas' })
    expect(within(seccion).getByText('Pensamiento analítico')).toBeInTheDocument()
    expect(within(seccion).getByText('Tu nivel: C · Nivel requerido: B')).toBeInTheDocument()
  })

  it('si fallan los recomendados, el resto del catálogo sigue funcionando sin error', async () => {
    vi.mocked(listarRecomendados).mockRejectedValue(httpError(500))
    renderPage()

    expect(await screen.findByRole('heading', { name: 'Habilidades interpersonales' })).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('al elegir una tarjeta abre el detalle con grados, cursos y micro-contenidos, y permite volver', async () => {
    vi.mocked(obtenerDetalle).mockResolvedValue(
      detalle({
        nivel_actual: 'B',
        nivel_requerido: 'A',
        cursos: [recurso({ id: 1, nombre: 'Comunicación asertiva', url_destino: '/cursos/1' })],
      }),
    )
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Comunicación efectiva/ }))

    expect(await screen.findByRole('heading', { name: 'Comunicación efectiva', level: 1 })).toBeInTheDocument()
    expect(obtenerDetalle).toHaveBeenCalledWith(1)
    expect(screen.getByText('Adapta su mensaje a cada audiencia.')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Cursos (1)' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Iniciar Comunicación asertiva' })).toBeInTheDocument()
    expect(screen.getByText('Aptos para desarrollo de conocimientos, no de competencias conductuales.')).toBeInTheDocument()
    expect(screen.getByText(/Nivel requerido: A/)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Volver al catálogo' }))
    expect(await screen.findByRole('heading', { name: 'Habilidades interpersonales' })).toBeInTheDocument()
  })

  it('el detalle sin oferta lo avisa', async () => {
    vi.mocked(obtenerDetalle).mockResolvedValue(detalle({ cursos: [], microcontenidos: [] }))
    renderPage('/formacion?competencia=2')

    expect(await screen.findByText('Aún no hay oferta formativa disponible para esta competencia.')).toBeInTheDocument()
  })

  it('una competencia que ya no está disponible muestra un mensaje claro', async () => {
    vi.mocked(obtenerDetalle).mockRejectedValue(httpError(404))
    renderPage('/formacion?competencia=9')

    expect(await screen.findByText('La competencia no está disponible')).toBeInTheDocument()
  })

  it('muestra el error del listado con Reintentar', async () => {
    vi.mocked(listarCatalogoCompleto).mockRejectedValueOnce(httpError(500))
    renderPage()
    const user = userEvent.setup()

    expect(await screen.findByText('No se pudo cargar el catálogo')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Reintentar' }))

    await waitFor(() => expect(screen.getByRole('heading', { name: 'Habilidades interpersonales' })).toBeInTheDocument())
  })

  it('muestra un estado vacío cuando no hay competencias', async () => {
    vi.mocked(listarCatalogoCompleto).mockResolvedValue([])
    renderPage()

    expect(await screen.findByText('Aún no hay competencias en el catálogo')).toBeInTheDocument()
  })
})
