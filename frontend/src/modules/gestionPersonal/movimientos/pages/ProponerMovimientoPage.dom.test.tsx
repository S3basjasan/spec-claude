// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { aplicable } from '../../../../test/politicasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerPoliticaAplicable } from '../../politicas/api'
import { buscarColaboradores, listarRespaldosDeAdministrador, obtenerContextoDeMovimiento, proponerMovimiento, type ContextoDeMovimiento } from '../api'
import { ProponerMovimientoPage } from './ProponerMovimientoPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  buscarColaboradores: vi.fn(),
  obtenerContextoDeMovimiento: vi.fn(),
  listarRespaldosDeAdministrador: vi.fn(),
  proponerMovimiento: vi.fn(),
}))
vi.mock('../../politicas/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../../politicas/api')>()),
  obtenerPoliticaAplicable: vi.fn(),
}))

function contexto(cambios: Partial<ContextoDeMovimiento> = {}): ContextoDeMovimiento {
  return {
    colaborador: { id: 3, codigo: 'EMP-003', nombre: 'Ana Núñez' },
    vinculo_vigente: true,
    posicion_actual: { id: 10, puesto: 'Analista de datos', unidad: 'Finanzas' },
    destinos: { items: [{ id: 11, puesto: 'Jefe de analítica', unidad: 'Finanzas', jefatura: 'Rosa Vera' }], total: 1 },
    politicas: {
      ascensos: { situacion: 'Vigente', numero: 1, parametros: { disponible: true, anticipacion_dias: 5, documento_adicional_obligatorio: false } },
      traslados: { situacion: 'Vigente', numero: 1, parametros: null },
    },
    ...cambios,
  }
}

function renderPage() {
  return renderRoutes(
    <>
      <Route path="/movimientos-personal/proponer" element={<ProponerMovimientoPage />} />
      <Route path="/movimientos-personal/:id" element={<p>Detalle de la propuesta</p>} />
    </>,
    '/movimientos-personal/proponer',
  )
}

async function elegirPersona(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('combobox', { name: 'Colaborador' }))
  await user.click(await screen.findByRole('option', { name: /Ana Núñez/ }))
}

async function elegirDestino(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('combobox', { name: 'Posición destino' }))
  await user.click(await screen.findByRole('option', { name: /Jefe de analítica/ }))
}

beforeEach(() => {
  vi.mocked(obtenerPoliticaAplicable).mockReset().mockResolvedValue(
    aplicable({ tipo: 'ascensos', parametros: { disponible: true, anticipacion_dias: 5, documento_adicional_obligatorio: false } }),
  )
  vi.mocked(buscarColaboradores).mockReset().mockResolvedValue([{ id: 3, codigo: 'EMP-003', nombre: 'Ana Núñez' }])
  vi.mocked(obtenerContextoDeMovimiento).mockReset().mockResolvedValue(contexto())
  vi.mocked(listarRespaldosDeAdministrador).mockReset().mockResolvedValue([])
  vi.mocked(proponerMovimiento).mockReset().mockResolvedValue({ id: 12 } as never)
})

describe('ProponerMovimientoPage', () => {
  it('muestra la situación actual y los destinos de Estructura al elegir a la persona (US4.1)', async () => {
    const user = userEvent.setup()
    renderPage()

    await elegirPersona(user)

    const situacion = await screen.findByLabelText('Situación actual')
    expect(situacion).toHaveTextContent('Analista de datos · Finanzas')
    expect(screen.getByText(/Al proponer se fija la versión de política vigente/)).toBeInTheDocument()
    expect(screen.getByText(/no modifica ninguna asignación/i)).toBeInTheDocument()
  })

  it('exige colaborador, destino, fecha efectiva y justificación antes de enviar (US4.6)', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Proponer movimiento' }))

    expect(await screen.findByText('Elige al colaborador.')).toBeInTheDocument()
    expect(screen.getByText('Ingresa una fecha válida (AAAA-MM-DD).')).toBeInTheDocument()
    expect(screen.getByText('Este dato es obligatorio.')).toBeInTheDocument()
    expect(proponerMovimiento).not.toHaveBeenCalled()

    await elegirPersona(user)
    await user.click(screen.getByRole('button', { name: 'Proponer movimiento' }))
    expect(await screen.findByText('Elige la posición destino.')).toBeInTheDocument()
    expect(proponerMovimiento).not.toHaveBeenCalled()
  })

  it('propone con clave de operación y abre el detalle, sin dar por cambiada la posición (US4.1)', async () => {
    const user = userEvent.setup()
    renderPage()
    await elegirPersona(user)
    await elegirDestino(user)
    await user.selectOptions(screen.getByLabelText('Tipo de movimiento'), 'Traslado')
    await user.type(screen.getByLabelText('Fecha efectiva'), '2026-12-01')
    await user.type(screen.getByLabelText('Justificación'), 'Reorganización del área')

    await user.click(screen.getByRole('button', { name: 'Proponer movimiento' }))

    await waitFor(() => expect(proponerMovimiento).toHaveBeenCalledTimes(1))
    const [colaboradorId, datos, clave] = vi.mocked(proponerMovimiento).mock.calls[0]
    expect(colaboradorId).toBe(3)
    expect(datos).toMatchObject({ tipo: 'Traslado', posicion_destino_id: 11, fecha_efectiva: '2026-12-01', motivo: 'Reorganización del área', documento_ids: [] })
    expect(clave).toMatch(/^[0-9a-f-]{36}$/)
    expect(await screen.findByText('Detalle de la propuesta')).toBeInTheDocument()
  })

  it('una persona sin vínculo vigente no admite propuesta y se explica', async () => {
    const user = userEvent.setup()
    vi.mocked(obtenerContextoDeMovimiento).mockResolvedValue(contexto({ vinculo_vigente: false, posicion_actual: null }))
    renderPage()

    await elegirPersona(user)

    expect(await screen.findByText(/no tiene un vínculo laboral vigente: no puede proponerse un movimiento/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Proponer movimiento' })).toBeDisabled()
  })

  it('un destino no disponible se explica en el campo y conserva lo escrito', async () => {
    const user = userEvent.setup()
    vi.mocked(proponerMovimiento).mockRejectedValue(
      httpError(422, { code: 'POSICION_NO_DISPONIBLE', message: 'La posición destino está suprimida.', errors: { posicion_destino_id: ['La posición destino está suprimida.'] } }),
    )
    renderPage()
    await elegirPersona(user)
    await elegirDestino(user)
    await user.type(screen.getByLabelText('Fecha efectiva'), '2026-12-01')
    await user.type(screen.getByLabelText('Justificación'), 'Desempeño')

    await user.click(screen.getByRole('button', { name: 'Proponer movimiento' }))

    expect((await screen.findAllByText('La posición destino está suprimida.')).length).toBeGreaterThan(0)
    expect(screen.getByLabelText('Justificación')).toHaveValue('Desempeño')
    expect(screen.getByLabelText('Fecha efectiva')).toHaveValue('2026-12-01')
  })

  it('con política sin configurar bloquea y no confunde la causa', async () => {
    vi.mocked(obtenerPoliticaAplicable).mockResolvedValue(aplicable({ tipo: 'ascensos', situacion: 'SinConfigurar', numero: null, parametros: null, inicio_vigencia: null }))
    renderPage()

    expect(await screen.findByText('Política sin configurar')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Proponer movimiento' })).toBeDisabled()
  })
})
