// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { listaDeResumenes, resumen } from '../../../../test/politicasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarPoliticas } from '../api'
import { PoliticasPage } from './PoliticasPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarPoliticas: vi.fn(),
}))

function renderPagina(entrada = '/politicas-personal') {
  return renderRoutes(<Route path="/politicas-personal" element={<PoliticasPage />} />, entrada)
}

beforeEach(() => {
  vi.mocked(listarPoliticas).mockReset().mockResolvedValue(listaDeResumenes())
})

describe('PoliticasPage', () => {
  it('muestra los siete tipos con su situación y distingue «sin configurar» de «no admite nuevas solicitudes»', async () => {
    renderPagina()

    const tabla = await screen.findByRole('table', { name: /políticas de personal por tipo/i })
    expect(within(tabla).getAllByRole('row')).toHaveLength(8)
    expect(within(tabla).getAllByText('Política sin configurar')).toHaveLength(5)
    expect(within(tabla).getByText('No admite nuevas solicitudes')).toBeInTheDocument()
    expect(within(tabla).getByText('Vigente')).toBeInTheDocument()
    expect(within(tabla).getByText('Versión 2')).toBeInTheDocument()
    expect(within(tabla).getByText('En preparación')).toBeInTheDocument()
    expect(screen.queryByText(/sin restricciones/i)).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver política de Permisos' })).toHaveAttribute('href', '/politicas-personal/permisos')
  })

  it('los filtros se guardan en la URL y se envían al servidor', async () => {
    const user = userEvent.setup()
    const { getByTestId } = renderPagina()
    await screen.findByRole('table')

    await user.selectOptions(screen.getByLabelText('Tipo de política'), 'ausencias')
    await user.selectOptions(screen.getByLabelText('Situación'), 'ConProgramada')

    await waitFor(() => expect(listarPoliticas).toHaveBeenLastCalledWith({ tipo: 'ausencias', situacion: 'ConProgramada' }, expect.anything()))
    expect(getByTestId('ubicacion').textContent).toContain('tipo=ausencias')
    expect(getByTestId('ubicacion').textContent).toContain('situacion=ConProgramada')
  })

  it('con filtros sin resultados ofrece limpiar y restablece la lista', async () => {
    const user = userEvent.setup()
    vi.mocked(listarPoliticas).mockResolvedValueOnce([])
    renderPagina('/politicas-personal?tipo=permisos')

    expect(await screen.findByText('Ningún tipo coincide con los filtros')).toBeInTheDocument()
    vi.mocked(listarPoliticas).mockResolvedValue([resumen()])
    await user.click(screen.getAllByRole('button', { name: 'Limpiar filtros' })[0])

    await waitFor(() => expect(listarPoliticas).toHaveBeenLastCalledWith({ tipo: undefined, situacion: undefined }, expect.anything()))
  })

  it('un error muestra el estado de error con Reintentar', async () => {
    const user = userEvent.setup()
    vi.mocked(listarPoliticas).mockRejectedValue(httpError(500))
    renderPagina()

    // Un error 5xx se reintenta una vez (con la espera de React Query) antes de mostrar el estado de error.
    expect(await screen.findByText('No se pudieron cargar las políticas', {}, { timeout: 4000 })).toBeInTheDocument()
    vi.mocked(listarPoliticas).mockResolvedValue(listaDeResumenes())
    await user.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('table')).toBeInTheDocument()
  })

  it('muestra el estado de carga mientras consulta', () => {
    vi.mocked(listarPoliticas).mockReturnValue(new Promise(() => undefined))
    renderPagina()

    expect(screen.getByRole('status', { name: 'Cargando políticas' })).toBeInTheDocument()
  })
})
