// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { fila, miAprendizaje } from '../../../../test/microFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { descargarCertificado, obtenerMiAprendizaje } from '../api'
import { MiAprendizajePage } from './MiAprendizajePage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerMiAprendizaje: vi.fn(),
  descargarCertificado: vi.fn(),
}))

function renderPage(entrada = '/micro-learning') {
  return renderRoutes(<Route path="/micro-learning" element={<MiAprendizajePage />} />, entrada)
}

beforeEach(() => {
  vi.mocked(obtenerMiAprendizaje).mockReset().mockResolvedValue(miAprendizaje())
  vi.mocked(descargarCertificado).mockReset().mockResolvedValue()
})

describe('MiAprendizajePage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(obtenerMiAprendizaje).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando micro-learning' })).toBeInTheDocument()
  })

  it('muestra las tarjetas de resumen, la nota de conocimientos y las pestañas con sus cantidades', async () => {
    renderPage()

    expect(await screen.findByRole('tab', { name: 'Asignado (1)' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Completado (1)' })).toBeInTheDocument()
    expect(screen.getByText('Horas acumuladas').nextSibling).toHaveTextContent('0,5')
    expect(screen.getByText('Certificados').nextSibling).toHaveTextContent('1')
    expect(screen.getAllByText(/refuerzan conocimientos/).length).toBeGreaterThan(0)
    expect(screen.getByText('Asignado uno')).toBeInTheDocument()
  })

  it('la pestaña Completado muestra el certificado y se guarda en la URL', async () => {
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('tab', { name: 'Completado (1)' }))

    expect(screen.getByTestId('ubicacion')).toHaveTextContent('tab=completado')
    expect(screen.getByText('Completado uno')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Descargar certificado de Completado uno' }))
    await waitFor(() => expect(descargarCertificado).toHaveBeenCalledWith(2, 'MICRO-0001'))
  })

  it('si no se puede descargar el certificado lo informa', async () => {
    vi.mocked(descargarCertificado).mockRejectedValue(httpError(500))
    renderPage('/micro-learning?tab=completado')
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Descargar certificado de Completado uno' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo descargar el certificado')
  })

  it('una pestaña vacía muestra un mensaje informativo', async () => {
    vi.mocked(obtenerMiAprendizaje).mockResolvedValue(miAprendizaje({ asignados: [], completados: [] }))
    renderPage()
    const user = userEvent.setup()

    expect(await screen.findByText('No tienes cápsulas asignadas por ahora.')).toBeInTheDocument()
    await user.click(screen.getByRole('tab', { name: 'Completado (0)' }))
    expect(screen.getByText('Aún no has completado cápsulas.')).toBeInTheDocument()
  })

  it('avisa si no se pudieron consultar las brechas pero muestra lo iniciado', async () => {
    vi.mocked(obtenerMiAprendizaje).mockResolvedValue(miAprendizaje({ avisos: ['brechas_no_disponibles'], asignados: [fila({ titulo: 'Iniciado', motivo: 'iniciado' })] }))
    renderPage()

    expect(await screen.findByText(/No pudimos consultar tus brechas/)).toBeInTheDocument()
    expect(screen.getByText('Iniciado')).toBeInTheDocument()
  })

  it('sin ficha de colaborador explica por qué no hay aprendizaje', async () => {
    vi.mocked(obtenerMiAprendizaje).mockRejectedValue(httpError(403, { message: 'Sin ficha' }))
    renderPage()

    expect(await screen.findByText(/no tiene una ficha de colaborador asociada/)).toBeInTheDocument()
  })

  it('un error de red ofrece reintentar', async () => {
    vi.mocked(obtenerMiAprendizaje).mockRejectedValueOnce(httpError(500)).mockResolvedValue(miAprendizaje())
    renderPage()

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Asignado uno')).toBeInTheDocument()
    expect(within(screen.getByRole('tabpanel')).getAllByRole('article')).toHaveLength(1)
  })
})
