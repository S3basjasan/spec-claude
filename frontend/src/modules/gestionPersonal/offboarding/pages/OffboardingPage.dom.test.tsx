// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fila, pagina, resumen } from '../../../../test/offboardingFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarSalidas, obtenerResumen } from '../api'
import { OffboardingPage } from './OffboardingPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarSalidas: vi.fn(),
  obtenerResumen: vi.fn(),
}))

function renderPage(entrada = '/offboarding') {
  return renderRoutes(<Route path="/offboarding" element={<OffboardingPage />} />, entrada)
}

beforeEach(() => {
  vi.mocked(listarSalidas).mockReset().mockResolvedValue(pagina([fila(), fila({ id: 6, estado: 'IncidenciaDeAplicacion', colaborador: { id: 4, codigo: 'EMP-004', nombre: 'Luis Paz' }, entrevista_estado: 'Completada' })]))
  vi.mocked(obtenerResumen).mockReset().mockResolvedValue(resumen())
})

describe('OffboardingPage', () => {
  it('lista las salidas con estado, cierre y entrevista distinguibles por texto (US-listado)', async () => {
    renderPage()

    const tabla = await screen.findByRole('table', { name: 'Salidas formalizadas de la organización' })
    expect(within(tabla).getByRole('link', { name: 'Abrir el expediente de Ana Núñez' })).toHaveAttribute('href', '/offboarding/5')
    expect(within(tabla).getByText('Programada')).toBeInTheDocument()
    expect(within(tabla).getByText('Incidencia de aplicación')).toBeInTheDocument()
    expect(within(tabla).getByText('Entrevista completada')).toBeInTheDocument()
    expect(within(tabla).getAllByText('Cierre pendiente')).toHaveLength(2)
  })

  it('muestra el resumen y filtra al elegir una tarjeta', async () => {
    const user = userEvent.setup()
    renderPage()

    const resumenLista = await screen.findByRole('list', { name: 'Resumen de salidas' })
    expect(within(resumenLista).getByText('Con incidencia de aplicación')).toBeInTheDocument()

    await user.click(within(resumenLista).getByRole('button', { name: /Con incidencia de aplicación/ }))

    expect(listarSalidas).toHaveBeenLastCalledWith(expect.objectContaining({ estado: 'IncidenciaDeAplicacion' }), expect.anything())
    expect(await screen.findByTestId('ubicacion')).toHaveTextContent('estado=IncidenciaDeAplicacion')
  })

  it('aplica los filtros de la URL', async () => {
    renderPage('/offboarding?motivo=Jubilacion&entrevista=NoRealizada')

    await screen.findByRole('table')

    expect(listarSalidas).toHaveBeenCalledWith(expect.objectContaining({ motivo: 'Jubilacion', entrevista: 'NoRealizada' }), expect.anything())
    expect(screen.getByRole('combobox', { name: 'Motivo' })).toHaveValue('Jubilacion')
  })

  it('distingue una lista vacía de un error de carga y permite reintentar', async () => {
    const user = userEvent.setup()
    vi.mocked(listarSalidas).mockResolvedValueOnce(pagina([]))
    const { unmount } = renderPage()
    expect(await screen.findByText('No hay salidas para estos filtros')).toBeInTheDocument()
    unmount()

    vi.mocked(listarSalidas).mockReset().mockRejectedValueOnce(new Error('x')).mockResolvedValue(pagina())
    renderPage()
    expect(await screen.findByText('No se pudieron cargar las salidas')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByRole('table')).toBeInTheDocument()
  })

  it('ofrece formalizar una salida', async () => {
    renderPage()

    expect(await screen.findByRole('link', { name: 'Formalizar salida' })).toHaveAttribute('href', '/offboarding/formalizar')
  })
})
