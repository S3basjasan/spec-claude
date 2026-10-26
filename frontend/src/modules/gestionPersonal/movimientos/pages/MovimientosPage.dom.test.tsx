// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { fila, pagina, resumen } from '../../../../test/movimientosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { buscarColaboradores, listarActuaciones, obtenerResumen } from '../api'
import { MovimientosPage } from './MovimientosPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarActuaciones: vi.fn(),
  obtenerResumen: vi.fn(),
  buscarColaboradores: vi.fn(),
}))

function renderPage(entrada = '/movimientos-personal') {
  return renderRoutes(<Route path="/movimientos-personal" element={<MovimientosPage />} />, entrada)
}

beforeEach(() => {
  vi.mocked(listarActuaciones).mockReset().mockResolvedValue(pagina())
  vi.mocked(obtenerResumen).mockReset().mockResolvedValue(resumen())
  vi.mocked(buscarColaboradores).mockReset().mockResolvedValue([{ id: 3, codigo: 'EMP-003', nombre: 'Ana Núñez' }])
})

describe('MovimientosPage', () => {
  it('lista tipo, colaborador, presentación, responsable, período y estado, con enlace al detalle (US2.1)', async () => {
    vi.mocked(listarActuaciones).mockResolvedValue(
      pagina([
        fila(),
        fila({ id: 2, tipo: 'Ascenso', categoria: 'Movimiento', estado: 'Programada', periodo: null, fecha_efectiva: '2026-12-01', en_nombre_de_colaborador: true }),
        fila({ id: 3, tipo: 'Traslado', categoria: 'Movimiento', estado: 'IncidenciaDeAplicacion', periodo: null, fecha_efectiva: '2026-10-20' }),
      ]),
    )
    renderPage()

    const tabla = await screen.findByRole('table', { name: 'Movimientos y novedades de la organización' })
    expect(within(tabla).getByText('Presentada', { selector: 'span' })).toBeInTheDocument()
    expect(within(tabla).getByText('Programada')).toBeInTheDocument()
    expect(within(tabla).getByText('Incidencia de aplicación')).toBeInTheDocument()
    expect(within(tabla).getByText('Efectiva el 2026-12-01')).toBeInTheDocument()
    expect(within(tabla).getByText('2026-10-26 → 2026-10-27')).toBeInTheDocument()
    expect(within(tabla).getByText('Rosa Vera (en nombre del colaborador)')).toBeInTheDocument()
    expect(within(tabla).getByRole('link', { name: 'Abrir Ascenso de Ana Núñez' })).toHaveAttribute('href', '/movimientos-personal/2')
  })

  it('muestra el resumen de pendientes, programados e incidencias (SC-008)', async () => {
    renderPage()

    const lista = await screen.findByRole('list', { name: 'Resumen de solicitudes' })
    expect(within(lista).getByText('Movimientos programados').previousSibling).toHaveTextContent('3')
    expect(within(lista).getByText('Incidencias de aplicación').previousSibling).toHaveTextContent('1')
  })

  it('«Sin registros» no es un error y un error de carga no es «Sin registros» (FR-036)', async () => {
    vi.mocked(listarActuaciones).mockResolvedValue(pagina([]))
    const vacio = renderPage()
    expect(await screen.findByText('Sin registros')).toBeInTheDocument()
    expect(screen.queryByText('No se pudieron cargar las solicitudes')).not.toBeInTheDocument()
    vacio.unmount()

    vi.mocked(listarActuaciones).mockRejectedValue(httpError(500))
    renderPage()
    expect(await screen.findByText('No se pudieron cargar las solicitudes')).toBeInTheDocument()
    expect(screen.queryByText('Sin registros')).not.toBeInTheDocument()
  })

  it('reintenta la carga tras un error', async () => {
    const user = userEvent.setup()
    vi.mocked(listarActuaciones).mockRejectedValueOnce(httpError(500)).mockResolvedValue(pagina())
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('table', { name: 'Movimientos y novedades de la organización' })).toBeInTheDocument()
  })

  it('los filtros viven en la URL y se envían al servidor', async () => {
    const user = userEvent.setup()
    renderPage()
    await screen.findByRole('table')

    await user.selectOptions(screen.getByLabelText('Tipo'), 'Ascenso')
    await user.selectOptions(screen.getByLabelText('Situación'), 'incidencias')

    await waitFor(() => expect(vi.mocked(listarActuaciones).mock.calls.at(-1)?.[0]).toMatchObject({ tipo: 'Ascenso', grupo: 'incidencias' }))
    expect(screen.getByTestId('ubicacion').textContent).toContain('tipo=Ascenso')
    expect(screen.getByTestId('ubicacion').textContent).toContain('grupo=incidencias')

    await user.click(screen.getByRole('button', { name: 'Limpiar filtros' }))
    await waitFor(() => expect(screen.getByTestId('ubicacion').textContent).toBe('/movimientos-personal'))
  })

  it('arranca con los filtros de la URL y pagina', async () => {
    const user = userEvent.setup()
    vi.mocked(listarActuaciones).mockResolvedValue(pagina([fila()], { current_page: 2, total: 45, last_page: 3, from: 21, to: 40 }))
    renderPage('/movimientos-personal?estado=EnRevision&page=2')

    await screen.findByRole('table')
    expect(vi.mocked(listarActuaciones).mock.calls[0][0]).toMatchObject({ estado: 'EnRevision', page: 2 })

    await user.click(screen.getByRole('button', { name: 'Siguiente' }))
    await waitFor(() => expect(screen.getByTestId('ubicacion').textContent).toContain('page=3'))
  })
})
